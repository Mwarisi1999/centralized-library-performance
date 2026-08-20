<?php

namespace Tests\Feature;

use App\Models\MonthlyReport;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\StaffProfile;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkEntry;
use Database\Seeders\ProjectCategorySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReturnedWorkEntryCorrectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, ProjectCategorySeeder::class]);
        $this->travelTo('2026-08-20 10:00:00');
    }

    public function test_owner_can_edit_returned_period_entry_with_existing_validation_and_history(): void
    {
        [$owner, , $entry, $report] = $this->workflow(MonthlyReport::STATUS_RETURNED_FOR_CORRECTION);
        $originalCode = $report->report_code;

        $this->actingAs($owner)->get(route('work-entries.edit', $entry))
            ->assertOk()
            ->assertSee('Save Corrections');
        $this->actingAs($owner)->patch(route('work-entries.update', $entry), $this->payload($entry, [
            'start_time' => '08:30',
            'end_time' => '10:00',
            'output_deliverable' => 'Corrected catalogue audit output.',
            'support_required' => 'Access to approved records.',
            'work_location' => '  ICT Lab  ',
            'user_id' => User::factory()->create()->id,
            'duration_minutes' => 9999,
        ]))->assertRedirect(route('work-entries.show', $entry));

        $entry->refresh();
        $this->assertSame($owner->id, $entry->user_id);
        $this->assertSame(90, $entry->duration_minutes);
        $this->assertSame('Corrected catalogue audit output.', $entry->output_deliverable);
        $this->assertSame('ICT Lab', $entry->work_location);
        $this->assertSame($report->id, $report->fresh()->id);
        $this->assertSame($originalCode, $report->report_code);
        $this->assertSame(MonthlyReport::STATUS_RETURNED_FOR_CORRECTION, $report->fresh()->status);
        $this->assertDatabaseHas('work_entry_activities', ['work_entry_id' => $entry->id, 'user_id' => $owner->id, 'event' => 'work_entry_updated']);
    }

    public function test_pending_and_approved_periods_are_locked(): void
    {
        foreach ([MonthlyReport::STATUS_PENDING_REVIEW, MonthlyReport::STATUS_APPROVED] as $status) {
            [$owner, , $entry] = $this->workflow($status);

            $this->actingAs($owner)->get(route('work-entries.show', $entry))
                ->assertOk()
                ->assertDontSee('Edit Entry')
                ->assertSee('This entry is locked');
            $this->actingAs($owner)->get(route('work-entries.edit', $entry))->assertForbidden();
            $this->actingAs($owner)->patch(route('work-entries.update', $entry), $this->payload($entry, ['output_deliverable' => 'Forbidden change.']))->assertForbidden();
            $this->assertSame('Original August output.', $entry->fresh()->output_deliverable);
        }
    }

    public function test_other_employee_supervisor_and_other_month_cannot_edit(): void
    {
        [$owner, $supervisor, $augustEntry] = $this->workflow(MonthlyReport::STATUS_RETURNED_FOR_CORRECTION);
        $other = $this->user();
        $julyEntry = $this->entry($owner, '2026-07-10', 'July output.');

        $this->actingAs($other)->get(route('work-entries.edit', $augustEntry))->assertForbidden();
        $this->actingAs($other)->patch(route('work-entries.update', $augustEntry), $this->payload($augustEntry, ['work_location' => 'Forged location']))->assertForbidden();
        $this->actingAs($supervisor)->get(route('work-entries.edit', $augustEntry))->assertForbidden();
        $this->actingAs($owner)->get(route('work-entries.edit', $julyEntry))->assertForbidden();
        $this->actingAs($owner)->patch(route('work-entries.update', $augustEntry), $this->payload($augustEntry, ['work_date' => '2026-07-31']))
            ->assertSessionHasErrors(['work_date' => 'The work date must remain within the returned monthly report period.']);
        $this->assertSame('2026-08-10', $augustEntry->fresh()->work_date->format('Y-m-d'));
    }

    public function test_correction_updates_live_report_and_resubmission_refreshes_snapshot_then_relocks_entry(): void
    {
        [$owner, , $entry, $report] = $this->workflow(MonthlyReport::STATUS_RETURNED_FOR_CORRECTION);
        $originalId = $report->id;
        $originalCode = $report->report_code;

        $this->actingAs($owner)->patch(route('work-entries.update', $entry), $this->payload($entry, [
            'output_deliverable' => 'Corrected responsive navigation.',
            'support_required' => 'Approved media assets required.',
        ]))->assertRedirect();
        $this->actingAs($owner)->get(route('my-work.monthly-report', ['month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertSee('Corrected responsive navigation.')
            ->assertSee('Approved media assets required.')
            ->assertDontSee('Original August output.');

        $this->actingAs($owner)->post(route('my-work.monthly-report.submit'), ['month' => 8, 'year' => 2026])->assertRedirect();

        $report->refresh();
        $this->assertSame($originalId, $report->id);
        $this->assertSame($originalCode, $report->report_code);
        $this->assertSame(MonthlyReport::STATUS_PENDING_REVIEW, $report->status);
        $this->assertSame(['Corrected responsive navigation.'], data_get($report->submitted_snapshot, 'narrative.key_achievements'));
        $this->assertSame(['Approved media assets required.'], data_get($report->submitted_snapshot, 'narrative.support_required'));
        $this->actingAs($owner)->get(route('work-entries.edit', $entry))->assertForbidden();
        $this->actingAs($owner)->patch(route('work-entries.update', $entry), $this->payload($entry, ['output_deliverable' => 'Late change.']))->assertForbidden();
        $this->assertSame('Corrected responsive navigation.', $entry->fresh()->output_deliverable);
    }

    /** @return array{User, User, WorkEntry, MonthlyReport} */
    private function workflow(string $status): array
    {
        static $sequence = 0;
        $sequence++;
        $owner = $this->user();
        $supervisor = $this->user('Campus Librarian');
        StaffProfile::create(['user_id' => $owner->id, 'staff_number' => 'COR-OWNER-'.$sequence, 'supervisor_id' => $supervisor->id, 'status' => 'active']);
        StaffProfile::create(['user_id' => $supervisor->id, 'staff_number' => 'COR-SUP-'.$sequence, 'status' => 'active']);
        $entry = $this->entry($owner, '2026-08-10', 'Original August output.');
        $report = MonthlyReport::create([
            'report_code' => 'MRP-2026-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
            'user_id' => $owner->id,
            'reviewer_id' => $supervisor->id,
            'submitted_by' => $owner->id,
            'reporting_month' => 8,
            'reporting_year' => 2026,
            'status' => $status,
            'submitted_at' => now()->subDay(),
            'returned_at' => $status === MonthlyReport::STATUS_RETURNED_FOR_CORRECTION ? now() : null,
            'approved_at' => $status === MonthlyReport::STATUS_APPROVED ? now() : null,
            'correction_reason' => $status === MonthlyReport::STATUS_RETURNED_FOR_CORRECTION ? 'Correct the source entry.' : null,
            'submitted_snapshot' => ['period' => ['month' => 8, 'year' => 2026, 'label' => 'August 2026'], 'staff' => ['name' => $owner->name, 'supervisor' => $supervisor->name], 'performance' => [], 'narrative' => ['key_achievements' => ['Original August output.'], 'challenges' => [], 'corrective_actions' => [], 'support_required' => [], 'planned_activities_next_month' => []]],
        ]);

        return [$owner, $supervisor, $entry, $report];
    }

    private function entry(User $owner, string $date, string $output): WorkEntry
    {
        static $sequence = 0;
        $sequence++;
        $project = Project::create(['project_code' => 'PRJ-COR-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT), 'title' => 'Correction project', 'project_category_id' => ProjectCategory::firstOrFail()->id, 'owner_id' => $owner->id, 'created_by' => $owner->id, 'start_date' => '2026-01-01', 'due_date' => '2026-12-31', 'scope' => 'university_wide', 'priority_level' => 'medium', 'progress_method' => 'manual', 'progress_percentage' => 0, 'status' => 'in_progress', 'is_active' => true]);
        $project->members()->attach($owner, ['joined_at' => now(), 'is_active' => true]);
        $task = Task::create(['task_code' => 'TSK-COR-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT), 'project_id' => $project->id, 'title' => 'Correction task', 'created_by' => $owner->id, 'assigned_by' => $owner->id, 'priority' => 'medium', 'status' => 'in_progress', 'progress_percentage' => 50, 'is_active' => true]);
        $task->assignees()->attach($owner, ['assigned_at' => $date, 'is_active' => true]);

        return WorkEntry::create(['entry_code' => 'WEN-COR-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT), 'user_id' => $owner->id, 'project_id' => $project->id, 'task_id' => $task->id, 'work_date' => $date, 'start_time' => '09:00', 'end_time' => '10:00', 'duration_minutes' => 60, 'work_description' => 'Original correction workflow description.', 'output_deliverable' => $output]);
    }

    private function payload(WorkEntry $entry, array $overrides = []): array
    {
        return array_merge(['project_id' => $entry->project_id, 'task_id' => $entry->task_id, 'subtask_id' => $entry->subtask_id, 'work_date' => $entry->work_date->format('Y-m-d'), 'work_location' => $entry->work_location, 'start_time' => '09:00', 'end_time' => '10:00', 'work_description' => $entry->work_description, 'output_deliverable' => $entry->output_deliverable, 'challenge_encountered' => $entry->challenge_encountered, 'corrective_action' => $entry->corrective_action, 'support_required' => $entry->support_required, 'planned_next_activity' => $entry->planned_next_activity, 'remarks' => $entry->remarks], $overrides);
    }

    private function user(string $role = 'Staff'): User
    {
        $user = User::factory()->create(['account_status' => 'active']);
        $user->assignRole($role);

        return $user;
    }
}

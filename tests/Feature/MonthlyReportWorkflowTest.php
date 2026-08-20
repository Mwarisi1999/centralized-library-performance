<?php

namespace Tests\Feature;

use App\Models\MonthlyReport;
use App\Models\MonthlyReportActivity;
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

class MonthlyReportWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, ProjectCategorySeeder::class]);
        $this->travelTo('2026-08-19 16:20:00');
    }

    public function test_owner_submission_creates_report_snapshot_reviewer_history_and_no_duplicate(): void
    {
        [$owner, $supervisor] = $this->staffWithSupervisor();
        $this->entry($owner, '2026-08-10', ['output_deliverable' => 'Completed catalogue audit.']);

        $this->actingAs($owner)->get($this->personalUrl())->assertOk();
        $this->assertDatabaseCount('monthly_reports', 0);

        $this->actingAs($owner)->post(route('my-work.monthly-report.submit'), $this->period())
            ->assertRedirect($this->personalUrl())
            ->assertSessionHas('success');

        $report = MonthlyReport::firstOrFail();
        $this->assertSame($owner->id, $report->user_id);
        $this->assertSame($supervisor->id, $report->reviewer_id);
        $this->assertSame($owner->id, $report->submitted_by);
        $this->assertSame(MonthlyReport::STATUS_PENDING_REVIEW, $report->status);
        $this->assertSame(['Completed catalogue audit.'], data_get($report->submitted_snapshot, 'narrative.key_achievements'));
        $this->assertDatabaseHas('monthly_report_activities', ['monthly_report_id' => $report->id, 'user_id' => $owner->id, 'event' => 'report_submitted']);

        $this->actingAs($owner)->post(route('my-work.monthly-report.submit'), $this->period())
            ->assertSessionHasErrors('report');
        $this->assertDatabaseCount('monthly_reports', 1);
        $this->assertDatabaseCount('monthly_report_activities', 1);
    }

    public function test_submission_preserves_existing_code_and_ignores_forged_workflow_fields(): void
    {
        [$owner, $supervisor] = $this->staffWithSupervisor();
        $forged = $this->user('Administrator');
        $report = MonthlyReport::create(['report_code' => 'MRP-2026-0001', 'user_id' => $owner->id, 'reporting_month' => 8, 'reporting_year' => 2026, 'status' => 'draft']);

        $this->actingAs($owner)->post(route('my-work.monthly-report.submit'), [
            ...$this->period(),
            'user_id' => $forged->id,
            'reviewer_id' => $forged->id,
            'status' => 'approved',
            'submitted_at' => '2000-01-01 00:00:00',
        ])->assertRedirect($this->personalUrl());

        $report->refresh();
        $this->assertSame('MRP-2026-0001', $report->report_code);
        $this->assertSame($owner->id, $report->user_id);
        $this->assertSame($supervisor->id, $report->reviewer_id);
        $this->assertSame(MonthlyReport::STATUS_PENDING_REVIEW, $report->status);
        $this->assertSame('2026-08-19 16:20:00', $report->submitted_at->format('Y-m-d H:i:s'));
    }

    public function test_no_active_recorded_supervisor_blocks_submission_without_admin_fallback(): void
    {
        $owner = $this->user();
        $admin = $this->user('Administrator');
        StaffProfile::create(['user_id' => $owner->id, 'staff_number' => 'NO-SUP', 'status' => 'active']);

        $this->actingAs($owner)->post(route('my-work.monthly-report.submit'), $this->period())
            ->assertSessionHasErrors(['report' => 'No active supervisor is assigned to your staff profile. Please contact the system administrator.']);
        $this->assertDatabaseCount('monthly_reports', 0);

        $inactiveSupervisor = $this->user('Campus Librarian');
        $inactiveSupervisor->update(['account_status' => 'inactive']);
        $owner->staffProfile->update(['supervisor_id' => $inactiveSupervisor->id]);
        $this->actingAs($owner)->post(route('my-work.monthly-report.submit'), $this->period())->assertSessionHasErrors('report');
        $this->assertNotSame($admin->id, $owner->staffProfile->fresh()->supervisor_id);
        $this->assertDatabaseCount('monthly_reports', 0);
    }

    public function test_only_stored_reviewer_can_view_and_approve_pending_report(): void
    {
        [$report, $owner, $supervisor] = $this->submittedReport();
        $otherSupervisor = $this->user('Campus Librarian');
        $administrator = $this->user('Administrator');

        $this->actingAs($supervisor)->get(route('monthly-reports.reviews.show', $report))->assertOk()->assertSee('Approve Report');
        $this->actingAs($owner)->get(route('monthly-reports.reviews.show', $report))->assertForbidden();
        $this->actingAs($otherSupervisor)->get(route('monthly-reports.reviews.show', $report))->assertForbidden();
        $this->actingAs($administrator)->post(route('monthly-reports.approve', $report))->assertForbidden();
        $this->actingAs($owner)->post(route('monthly-reports.approve', $report))->assertForbidden();

        $snapshot = $report->submitted_snapshot;
        $this->actingAs($supervisor)->post(route('monthly-reports.approve', $report))
            ->assertRedirect(route('monthly-reports.reviews.index'));

        $report->refresh();
        $this->assertSame(MonthlyReport::STATUS_APPROVED, $report->status);
        $this->assertNull($report->approval_remark);
        $this->assertNotNull($report->reviewed_at);
        $this->assertNotNull($report->approved_at);
        $this->assertSame($snapshot, $report->submitted_snapshot);
        $this->assertDatabaseHas('monthly_report_activities', ['monthly_report_id' => $report->id, 'user_id' => $supervisor->id, 'event' => 'report_approved']);
        $this->actingAs($supervisor)->post(route('monthly-reports.return', $report), ['correction_reason' => 'Too late.'])->assertForbidden();
        $this->actingAs($owner)->post(route('my-work.monthly-report.submit'), $this->period())->assertSessionHasErrors('report');
    }

    public function test_reviewer_can_return_reason_is_required_and_owner_can_resubmit_updated_snapshot(): void
    {
        [$report, $owner, $supervisor, $entry] = $this->submittedReport(true);

        $this->actingAs($supervisor)->post(route('monthly-reports.return', $report), ['correction_reason' => '   '])
            ->assertSessionHasErrors('correction_reason');
        $this->actingAs($supervisor)->post(route('monthly-reports.return', $report), ['correction_reason' => '  Please clarify the support required.  '])
            ->assertRedirect(route('monthly-reports.reviews.index'));

        $report->refresh();
        $this->assertSame(MonthlyReport::STATUS_RETURNED_FOR_CORRECTION, $report->status);
        $this->assertSame('Please clarify the support required.', $report->correction_reason);
        $this->assertNotNull($report->returned_at);
        $this->actingAs($owner)->get($this->personalUrl())->assertOk()->assertSee('Please clarify the support required.')->assertSee('Resubmit for Review');

        $entry->update(['support_required' => 'Access to approved media assets.']);
        $this->travelTo('2026-08-20 11:15:00');
        $this->actingAs($owner)->post(route('my-work.monthly-report.submit'), $this->period())->assertRedirect($this->personalUrl());

        $report->refresh();
        $this->assertSame(MonthlyReport::STATUS_PENDING_REVIEW, $report->status);
        $this->assertSame(['Access to approved media assets.'], data_get($report->submitted_snapshot, 'narrative.support_required'));
        $this->assertNull($report->correction_reason);
        $this->assertSame(['report_submitted', 'report_returned', 'report_resubmitted'], $report->activities()->orderBy('id')->pluck('event')->all());
        $this->assertSame('MRP-2026-0001', $report->report_code);
    }

    public function test_pending_snapshot_is_stable_and_excludes_other_users_and_months(): void
    {
        [$owner] = $this->staffWithSupervisor();
        $other = $this->user();
        $august = $this->entry($owner, '2026-08-10', ['output_deliverable' => 'Submitted August output.']);
        $this->entry($owner, '2026-07-10', ['output_deliverable' => 'July output.']);
        $this->entry($other, '2026-08-10', ['output_deliverable' => 'Other user output.']);
        $this->actingAs($owner)->post(route('my-work.monthly-report.submit'), $this->period());
        $report = MonthlyReport::firstOrFail();

        $august->update(['output_deliverable' => 'Changed after submission.']);
        $response = $this->actingAs($owner)->get($this->personalUrl())->assertOk();

        $response->assertSee('Submitted August output.')
            ->assertDontSee('Changed after submission.')
            ->assertDontSee('July output.')
            ->assertDontSee('Other user output.');
        $this->assertSame(['Submitted August output.'], data_get($report->fresh()->submitted_snapshot, 'narrative.key_achievements'));
    }

    public function test_review_queue_is_personal_pending_only_and_gets_are_read_only(): void
    {
        [$mine, , $supervisor] = $this->submittedReport();
        [$other, , $otherSupervisor] = $this->submittedReport();
        $approved = $this->reportFor($supervisor, MonthlyReport::STATUS_APPROVED, 'MRP-2026-0098');
        $returned = $this->reportFor($supervisor, MonthlyReport::STATUS_RETURNED_FOR_CORRECTION, 'MRP-2026-0099');
        $counts = [MonthlyReport::count(), MonthlyReportActivity::count()];

        $response = $this->actingAs($supervisor)->get(route('monthly-reports.reviews.index'))->assertOk();
        $this->assertEquals([$mine->id], $response->viewData('reports')->getCollection()->pluck('id')->all());
        $response->assertDontSee($other->report_code)->assertDontSee($approved->report_code)->assertDontSee($returned->report_code);
        $this->actingAs($otherSupervisor)->get(route('monthly-reports.reviews.index'))->assertOk()->assertSee($other->report_code);
        $this->assertSame($counts, [MonthlyReport::count(), MonthlyReportActivity::count()]);
    }

    /** @return array{User, User} */
    private function staffWithSupervisor(): array
    {
        $owner = $this->user();
        $supervisor = $this->user('Campus Librarian');
        $this->sequence++;
        StaffProfile::create(['user_id' => $owner->id, 'staff_number' => 'MR-OWNER-'.$this->sequence, 'supervisor_id' => $supervisor->id, 'status' => 'active']);
        StaffProfile::create(['user_id' => $supervisor->id, 'staff_number' => 'MR-SUP-'.$this->sequence, 'status' => 'active']);

        return [$owner, $supervisor];
    }

    /** @return array{MonthlyReport, User, User, WorkEntry} */
    private function submittedReport(bool $withEntry = false): array
    {
        [$owner, $supervisor] = $this->staffWithSupervisor();
        $entry = $this->entry($owner, '2026-08-10', $withEntry ? ['output_deliverable' => 'Initial submitted output.'] : []);
        $this->actingAs($owner)->post(route('my-work.monthly-report.submit'), $this->period());

        return [MonthlyReport::query()->where('user_id', $owner->id)->firstOrFail(), $owner, $supervisor, $entry];
    }

    private function reportFor(User $reviewer, string $status, string $code): MonthlyReport
    {
        $owner = $this->user();

        return MonthlyReport::create(['report_code' => $code, 'user_id' => $owner->id, 'reviewer_id' => $reviewer->id, 'submitted_by' => $owner->id, 'reporting_month' => 7, 'reporting_year' => 2026, 'status' => $status, 'submitted_at' => now(), 'submitted_snapshot' => ['period' => ['month' => 7, 'year' => 2026, 'label' => 'July 2026'], 'staff' => ['name' => $owner->name], 'performance' => [], 'narrative' => []]]);
    }

    private function entry(User $user, string $date, array $overrides = []): WorkEntry
    {
        $this->sequence++;
        $category = ProjectCategory::firstOrFail();
        $project = Project::create(['project_code' => 'PRJ-MRW-'.str_pad((string) $this->sequence, 4, '0', STR_PAD_LEFT), 'title' => 'Report workflow project', 'project_category_id' => $category->id, 'owner_id' => $user->id, 'created_by' => $user->id, 'start_date' => '2026-01-01', 'due_date' => '2026-12-31', 'scope' => 'university_wide', 'priority_level' => 'medium', 'progress_method' => 'manual', 'progress_percentage' => 0, 'status' => 'in_progress', 'is_active' => true]);
        $task = Task::create(['task_code' => 'TSK-MRW-'.str_pad((string) $this->sequence, 4, '0', STR_PAD_LEFT), 'project_id' => $project->id, 'title' => 'Report workflow task', 'created_by' => $user->id, 'assigned_by' => $user->id, 'priority' => 'medium', 'status' => 'in_progress', 'progress_percentage' => 50, 'is_active' => true]);
        $task->assignees()->attach($user, ['assigned_at' => $date, 'is_active' => true]);

        return WorkEntry::create(array_merge(['entry_code' => 'WEN-MRW-'.str_pad((string) $this->sequence, 4, '0', STR_PAD_LEFT), 'user_id' => $user->id, 'project_id' => $project->id, 'task_id' => $task->id, 'work_date' => $date, 'start_time' => '09:00', 'end_time' => '10:00', 'duration_minutes' => 60, 'work_description' => 'Monthly report workflow test entry.'], $overrides));
    }

    private function user(string $role = 'Staff'): User
    {
        $user = User::factory()->create(['account_status' => 'active']);
        $user->assignRole($role);

        return $user;
    }

    private function period(): array
    {
        return ['month' => 8, 'year' => 2026];
    }

    private function personalUrl(): string
    {
        return route('my-work.monthly-report', $this->period());
    }
}

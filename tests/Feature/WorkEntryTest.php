<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkEntry;
use Database\Seeders\ProjectCategorySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkEntryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, ProjectCategorySeeder::class]);
    }

    public function test_assigned_user_creates_entry_with_code_and_automatic_duration(): void
    {
        [$project, $task, $user] = $this->assignment();
        $before = [$project->progress_percentage, $task->progress_percentage];
        $this->actingAs($user)->post(route('work-entries.store'), $this->payload($project, $task, ['user_id' => User::factory()->create()->id, 'duration_minutes' => 9999]))->assertRedirect(route('my-work.index'));
        $entry = WorkEntry::firstOrFail();
        $this->assertSame('WEN-'.now()->year.'-0001', $entry->entry_code);
        $this->assertSame($user->id, $entry->user_id);
        $this->assertSame(210, $entry->duration_minutes);
        $this->assertSame('3.5 hours', $entry->formatted_duration);
        $this->assertSame($before, [$project->fresh()->progress_percentage, $task->fresh()->progress_percentage]);
    }

    public function test_structured_narrative_fields_are_trimmed_saved_and_visible_on_detail(): void
    {
        [$project, $task, $user] = $this->assignment();

        $this->actingAs($user)->post(route('work-entries.store'), $this->payload($project, $task, [
            'challenge_encountered' => '  Mobile layout had inconsistent spacing.  ',
            'corrective_action' => '  Adjusted responsive breakpoints.  ',
            'support_required' => '  Access to final approved media assets.  ',
            'planned_next_activity' => '  Complete accessibility improvements.  ',
            'user_id' => User::factory()->create()->id,
        ]))->assertRedirect(route('my-work.index'));

        $entry = WorkEntry::firstOrFail();
        $this->assertSame($user->id, $entry->user_id);
        $this->assertSame('Mobile layout had inconsistent spacing.', $entry->challenge_encountered);
        $this->assertSame('Adjusted responsive breakpoints.', $entry->corrective_action);
        $this->assertSame('Access to final approved media assets.', $entry->support_required);
        $this->assertSame('Complete accessibility improvements.', $entry->planned_next_activity);

        $this->actingAs($user)->get(route('work-entries.show', $entry))
            ->assertOk()
            ->assertSee('Challenge Encountered')
            ->assertSee($entry->challenge_encountered)
            ->assertSee($entry->corrective_action)
            ->assertSee($entry->support_required)
            ->assertSee($entry->planned_next_activity);
    }

    public function test_work_location_is_optional_trimmed_validated_and_visible_on_detail(): void
    {
        [$project, $task, $user] = $this->assignment();

        $this->actingAs($user)->post(route('work-entries.store'), $this->payload($project, $task, [
            'work_location' => '  Engineering Library  ',
        ]))->assertRedirect(route('my-work.index'));

        $entry = WorkEntry::firstOrFail();
        $this->assertSame('Engineering Library', $entry->work_location);
        $this->actingAs($user)->get(route('work-entries.show', $entry))
            ->assertOk()->assertSee('Work Location')->assertSee('Engineering Library');

        [$otherProject, $otherTask, $otherUser] = $this->assignment();
        $this->actingAs($otherUser)->post(route('work-entries.store'), $this->payload($otherProject, $otherTask, [
            'work_location' => str_repeat('L', 256),
        ]))->assertSessionHasErrors('work_location');
    }

    public function test_unassigned_user_and_inaccessible_project_are_rejected(): void
    {
        [$project, $task] = $this->assignment();
        $outsider = $this->user('Staff');
        $this->actingAs($outsider)->post(route('work-entries.store'), $this->payload($project, $task))->assertSessionHasErrors(['project_id', 'task_id']);
    }

    public function test_project_member_cannot_record_directly_against_unassigned_task(): void
    {
        [$project, $task] = $this->assignment();
        $member = $this->user('Staff');
        $project->members()->attach($member, ['joined_at' => now(), 'is_active' => true]);

        $this->actingAs($member)->post(route('work-entries.store'), $this->payload($project, $task))
            ->assertSessionHasErrors('task_id');
        $this->assertDatabaseCount('work_entries', 0);
    }

    public function test_project_task_and_task_subtask_mismatches_are_rejected(): void
    {
        [$project, $task, $user] = $this->assignment();
        [$otherProject, $otherTask] = $this->assignment($user);
        $subtask = $this->subtask($otherTask, $user);
        $this->actingAs($user)->post(route('work-entries.store'), $this->payload($project, $otherTask))->assertSessionHasErrors('task_id');
        $this->actingAs($user)->post(route('work-entries.store'), $this->payload($project, $task, ['subtask_id' => $subtask->id]))->assertSessionHasErrors('subtask_id');
    }

    public function test_dropdown_only_contains_subtasks_assigned_to_authenticated_user(): void
    {
        [$project, $task, $user] = $this->assignment();
        $otherUser = $this->user('Staff');
        $mine = $this->subtask($task, $user);
        $theirs = $this->subtask($task, $otherUser);

        $this->actingAs($user)->get(route('work-entries.create'))
            ->assertOk()
            ->assertSee($mine->subtask_code)
            ->assertDontSee($theirs->subtask_code)
            ->assertSee('Record directly against task (optional)');

        $this->actingAs($user)->post(route('work-entries.store'), $this->payload($project, $task, ['subtask_id' => $mine->id]))
            ->assertRedirect(route('my-work.index'));
        $this->assertDatabaseHas('work_entries', ['user_id' => $user->id, 'subtask_id' => $mine->id]);
    }

    public function test_forged_request_for_another_users_subtask_is_rejected(): void
    {
        [$project, $task, $user] = $this->assignment();
        $otherSubtask = $this->subtask($task, $this->user('Staff'));

        $this->actingAs($user)->post(route('work-entries.store'), $this->payload($project, $task, ['subtask_id' => $otherSubtask->id]))
            ->assertSessionHasErrors('subtask_id');
        $this->assertDatabaseCount('work_entries', 0);
    }

    public function test_date_time_and_description_validation(): void
    {
        [$project, $task, $user] = $this->assignment();
        $this->actingAs($user)->post(route('work-entries.store'), $this->payload($project, $task, ['work_date' => now()->addDay()->format('Y-m-d'), 'start_time' => '12:30', 'end_time' => '09:00', 'work_description' => '']))->assertSessionHasErrors(['work_date', 'end_time', 'work_description']);
        $this->actingAs($user)->post(route('work-entries.store'), $this->payload($project, $task, ['start_time' => '09:00', 'end_time' => '09:00']))->assertSessionHasErrors('end_time');
        $this->actingAs($user)->post(route('work-entries.store'), $this->payload($project, $task, ['work_description' => '   ']))->assertSessionHasErrors('work_description');
    }

    public function test_all_required_fields_are_enforced_individually(): void
    {
        [$project, $task, $user] = $this->assignment();
        foreach (['work_date', 'project_id', 'task_id', 'work_description', 'start_time', 'end_time'] as $field) {
            $payload = $this->payload($project, $task);
            unset($payload[$field]);
            $this->actingAs($user)->post(route('work-entries.store'), $payload)->assertSessionHasErrors($field);
        }
    }

    public function test_today_and_yesterday_are_allowed_but_tomorrow_is_rejected(): void
    {
        [$project, $task, $user] = $this->assignment();
        $this->actingAs($user)->post(route('work-entries.store'), $this->payload($project, $task, ['work_date' => today()->format('Y-m-d')]))->assertRedirect();
        $this->actingAs($user)->post(route('work-entries.store'), $this->payload($project, $task, ['work_date' => today()->subDay()->format('Y-m-d')]))->assertRedirect();
        $this->actingAs($user)->post(route('work-entries.store'), $this->payload($project, $task, ['work_date' => today()->addDay()->format('Y-m-d')]))->assertSessionHasErrors('work_date');
    }

    public function test_cancelled_or_inactive_task_and_subtask_are_rejected(): void
    {
        [$project, $task, $user] = $this->assignment();
        $task->update(['status' => 'cancelled']);
        $this->actingAs($user)->post(route('work-entries.store'), $this->payload($project, $task))->assertSessionHasErrors('task_id');

        $task->update(['status' => 'in_progress']);
        $subtask = $this->subtask($task, $user);
        $subtask->update(['is_active' => false]);
        $this->actingAs($user)->post(route('work-entries.store'), $this->payload($project, $task, ['subtask_id' => $subtask->id]))->assertSessionHasErrors('subtask_id');
    }

    public function test_weekend_and_nullable_optional_fields_are_allowed(): void
    {
        [$project, $task, $user] = $this->assignment();
        $this->travelTo('2026-08-16 12:00:00');
        $this->actingAs($user)->post(route('work-entries.store'), $this->payload($project, $task, [
            'work_date' => '2026-08-16',
            'output_deliverable' => null,
            'challenge_encountered' => null,
            'corrective_action' => null,
            'support_required' => null,
            'planned_next_activity' => null,
            'remarks' => null,
        ]))->assertRedirect();
        $entry = WorkEntry::firstOrFail();
        $this->assertNull($entry->challenge_encountered);
        $this->assertNull($entry->corrective_action);
        $this->assertNull($entry->support_required);
        $this->assertNull($entry->planned_next_activity);
        $this->assertNull($entry->work_location);
        $this->actingAs($user)->get(route('work-entries.show', $entry))->assertOk();
    }

    public function test_work_entry_does_not_change_subtask_task_or_project_progress(): void
    {
        [$project, $task, $user] = $this->assignment(null, ['progress_percentage' => 40]);
        $subtask = $this->subtask($task, $user, 25);
        $project->update(['progress_method' => 'manual', 'progress_percentage' => 30]);
        $this->actingAs($user)->post(route('work-entries.store'), $this->payload($project, $task, ['subtask_id' => $subtask->id]));
        $this->assertSame(25.0, (float) $subtask->fresh()->progress_percentage);
        $this->assertSame(40.0, (float) $task->fresh()->progress_percentage);
        $this->assertSame(30.0, (float) $project->fresh()->progress_percentage);
    }

    public function test_owner_can_view_entry_but_arbitrary_user_cannot(): void
    {
        [$project, $task, $user] = $this->assignment();
        $this->actingAs($user)->post(route('work-entries.store'), $this->payload($project, $task));
        $entry = WorkEntry::firstOrFail();
        $this->actingAs($user)->get(route('work-entries.show', $entry))->assertOk()->assertSee('3.5 hours');
        $this->actingAs($this->user('Staff'))->get(route('work-entries.show', $entry))->assertForbidden();
    }

    public function test_my_work_only_lists_authenticated_users_recent_entries(): void
    {
        [$project, $task, $user] = $this->assignment();
        $this->actingAs($user)->post(route('work-entries.store'), $this->payload($project, $task));
        $this->actingAs($user)->get(route('my-work.index'))->assertOk()->assertSee(WorkEntry::first()->entry_code)->assertSee('3.5 hours');
    }

    public function test_overlapping_sessions_are_rejected_and_do_not_create_entries(): void
    {
        [$project, $task, $user] = $this->assignment();
        $this->travelTo('2026-08-16 18:00:00');
        $this->actingAs($user)->post(route('work-entries.store'), $this->payload($project, $task, [
            'work_date' => '2026-08-16',
        ]))->assertRedirect(route('my-work.index'));

        foreach ([['10:00', '11:00'], ['08:00', '10:00'], ['12:00', '14:00'], ['08:00', '13:00'], ['09:00', '12:30']] as [$start, $end]) {
            $this->actingAs($user)->from(route('work-entries.create'))->post(route('work-entries.store'), $this->payload($project, $task, [
                'work_date' => '2026-08-16', 'start_time' => $start, 'end_time' => $end,
            ]))->assertRedirect(route('work-entries.create'))
                ->assertSessionHasErrors(['start_time' => 'This work session overlaps with WEN-2026-0001 (09:00–12:30).'])
                ->assertSessionHasInput('start_time', $start)
                ->assertSessionHasInput('end_time', $end);
        }

        $this->assertDatabaseCount('work_entries', 1);
    }

    public function test_adjacent_and_separate_sessions_are_allowed_with_calculated_durations(): void
    {
        [$project, $task, $user] = $this->assignment();
        $this->travelTo('2026-08-16 18:00:00');
        $this->actingAs($user)->post(route('work-entries.store'), $this->payload($project, $task, ['work_date' => '2026-08-16']));

        foreach ([['07:00', '09:00', 120], ['12:30', '14:00', 90], ['14:00', '16:00', 120]] as [$start, $end, $minutes]) {
            $this->actingAs($user)->post(route('work-entries.store'), $this->payload($project, $task, [
                'work_date' => '2026-08-16', 'start_time' => $start, 'end_time' => $end,
            ]))->assertRedirect(route('my-work.index'));
            $this->assertDatabaseHas('work_entries', ['user_id' => $user->id, 'start_time' => $start, 'end_time' => $end, 'duration_minutes' => $minutes]);
        }
    }

    public function test_same_times_on_another_date_or_for_another_user_are_allowed(): void
    {
        [$project, $task, $user] = $this->assignment();
        $this->travelTo('2026-08-16 18:00:00');
        $this->actingAs($user)->post(route('work-entries.store'), $this->payload($project, $task, ['work_date' => '2026-08-16']));
        $this->actingAs($user)->post(route('work-entries.store'), $this->payload($project, $task, ['work_date' => '2026-08-15']))->assertRedirect(route('my-work.index'));

        [$otherProject, $otherTask, $otherUser] = $this->assignment();
        $this->actingAs($otherUser)->post(route('work-entries.store'), $this->payload($otherProject, $otherTask, ['work_date' => '2026-08-16']))->assertRedirect(route('my-work.index'));

        $this->assertDatabaseCount('work_entries', 3);
    }

    public function test_overlap_is_rejected_across_different_projects_tasks_and_subtasks(): void
    {
        [$project, $task, $user] = $this->assignment();
        [$otherProject, $otherTask] = $this->assignment($user);
        $subtask = $this->subtask($otherTask, $user);
        $this->travelTo('2026-08-16 18:00:00');
        $this->actingAs($user)->post(route('work-entries.store'), $this->payload($project, $task, ['work_date' => '2026-08-16']));

        $this->actingAs($user)->post(route('work-entries.store'), $this->payload($otherProject, $otherTask, [
            'subtask_id' => $subtask->id, 'work_date' => '2026-08-16', 'start_time' => '11:00', 'end_time' => '13:00',
        ]))->assertSessionHasErrors('start_time');

        $this->assertDatabaseCount('work_entries', 1);
    }

    public function test_soft_deleted_entry_does_not_block_a_new_session(): void
    {
        [$project, $task, $user] = $this->assignment();
        $this->travelTo('2026-08-16 18:00:00');
        $this->actingAs($user)->post(route('work-entries.store'), $this->payload($project, $task, ['work_date' => '2026-08-16']));
        WorkEntry::firstOrFail()->delete();

        $this->actingAs($user)->post(route('work-entries.store'), $this->payload($project, $task, ['work_date' => '2026-08-16']))
            ->assertRedirect(route('my-work.index'));

        $this->assertSame(1, WorkEntry::query()->count());
        $this->assertSame(2, WorkEntry::withTrashed()->count());
    }

    private function assignment(?User $user = null, array $taskOverrides = []): array
    {
        static $sequence = 0;
        $sequence++;
        $owner = $this->user('Administrator');
        $user ??= $this->user('Staff');
        $project = Project::create(['project_code' => 'PRJ-WEN-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT), 'title' => 'Work Entry Project '.$sequence, 'project_category_id' => ProjectCategory::firstOrFail()->id, 'owner_id' => $owner->id, 'created_by' => $owner->id, 'start_date' => '2026-01-01', 'due_date' => '2026-12-31', 'scope' => 'university_wide', 'priority_level' => 'medium', 'progress_method' => 'manual', 'progress_percentage' => 0, 'status' => 'in_progress', 'is_active' => true]);
        $project->members()->attach($user, ['joined_at' => now(), 'is_active' => true]);
        $task = Task::create(array_merge(['task_code' => 'TSK-WEN-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT), 'project_id' => $project->id, 'title' => 'Work Entry Task '.$sequence, 'created_by' => $owner->id, 'assigned_by' => $owner->id, 'priority' => 'medium', 'status' => 'in_progress', 'progress_percentage' => 0, 'is_active' => true], $taskOverrides));
        $task->assignees()->attach($user, ['assigned_at' => now(), 'is_active' => true]);

        return [$project, $task, $user];
    }

    private function subtask(Task $task, User $user, float $progress = 0): Subtask
    {
        static $sequence = 0;
        $sequence++;

        return Subtask::create(['subtask_code' => 'SUB-WEN-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT), 'task_id' => $task->id, 'title' => 'Work Entry Subtask', 'created_by' => $task->created_by, 'assigned_to' => $user->id, 'priority' => 'medium', 'status' => 'in_progress', 'progress_percentage' => $progress, 'is_active' => true]);
    }

    private function payload(Project $project, Task $task, array $overrides = []): array
    {
        return array_merge(['project_id' => $project->id, 'task_id' => $task->id, 'subtask_id' => null, 'work_date' => now()->format('Y-m-d'), 'start_time' => '09:00', 'end_time' => '12:30', 'work_description' => 'Prepared and refined the staff training presentation materials.', 'output_deliverable' => 'Updated training presentation slides.', 'challenge_encountered' => null, 'corrective_action' => null, 'support_required' => null, 'planned_next_activity' => null, 'remarks' => null], $overrides);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['account_status' => 'active']);
        $user->assignRole($role);

        return $user;
    }
}

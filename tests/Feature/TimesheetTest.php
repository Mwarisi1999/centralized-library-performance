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

class TimesheetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, ProjectCategorySeeder::class]);
    }

    public function test_user_only_sees_own_entries_and_user_id_query_is_ignored(): void
    {
        [$user, $project, $task] = $this->context();
        [$other, $otherProject, $otherTask] = $this->context();
        $mine = $this->entry($user, $project, $task, ['entry_code' => 'WEN-MINE']);
        $theirs = $this->entry($other, $otherProject, $otherTask, ['entry_code' => 'WEN-THEIRS']);

        $this->actingAs($user)->get(route('my-work.timesheet', ['user_id' => $other->id]))
            ->assertOk()->assertSee($mine->entry_code)->assertDontSee($theirs->entry_code);
    }

    public function test_current_month_is_default_and_date_range_filters_entries(): void
    {
        $this->travelTo('2026-08-16 12:00:00');
        [$user, $project, $task] = $this->context();
        $current = $this->entry($user, $project, $task, ['entry_code' => 'WEN-CURRENT', 'work_date' => '2026-08-10']);
        $old = $this->entry($user, $project, $task, ['entry_code' => 'WEN-OLD', 'work_date' => '2026-07-31']);
        $response = $this->actingAs($user)->get(route('my-work.timesheet'))->assertOk()->assertSee($current->entry_code)->assertDontSee($old->entry_code);
        $this->assertSame('2026-08-01', $response->viewData('filters')['date_from']);
        $this->assertSame('2026-08-16', $response->viewData('filters')['date_to']);
    }

    public function test_project_task_and_subtask_filters_and_direct_entries_work(): void
    {
        [$user, $project, $task, $subtask] = $this->context(true);
        [$sameUser, $otherProject, $otherTask] = $this->context(false, $user);
        $direct = $this->entry($user, $project, $task, ['entry_code' => 'WEN-DIRECT']);
        $specific = $this->entry($user, $project, $task, ['entry_code' => 'WEN-SUBTASK', 'subtask_id' => $subtask->id]);
        $other = $this->entry($sameUser, $otherProject, $otherTask, ['entry_code' => 'WEN-OTHER']);

        $this->actingAs($user)->get(route('my-work.timesheet', ['project_id' => $project->id]))->assertSee($direct->entry_code)->assertSee($specific->entry_code)->assertDontSee($other->entry_code);
        $this->actingAs($user)->get(route('my-work.timesheet', ['task_id' => $task->id]))->assertSee($specific->entry_code)->assertDontSee($other->entry_code);
        $this->actingAs($user)->get(route('my-work.timesheet', ['subtask_id' => $subtask->id]))->assertSee($specific->entry_code)->assertDontSee($direct->entry_code);
        $this->actingAs($user)->get(route('my-work.timesheet'))->assertSee('Direct task work');
    }

    public function test_totals_reflect_active_filters(): void
    {
        [$user, $project, $task, $subtask] = $this->context(true);
        $this->entry($user, $project, $task, ['duration_minutes' => 210]);
        $this->entry($user, $project, $task, ['duration_minutes' => 360, 'subtask_id' => $subtask->id]);
        $response = $this->actingAs($user)->get(route('my-work.timesheet'))->assertOk()->assertSee('9.5 hours');
        $this->assertSame(['entries' => 2, 'minutes' => 570, 'projects' => 1, 'tasks' => 1, 'subtasks' => 1], $response->viewData('totals'));
    }

    public function test_invalid_dates_and_reversed_range_are_rejected(): void
    {
        [$user] = $this->context();
        $this->actingAs($user)->get(route('my-work.timesheet', ['date_from' => 'invalid']))->assertSessionHasErrors('date_from');
        $this->actingAs($user)->get(route('my-work.timesheet', ['date_to' => 'invalid']))->assertSessionHasErrors('date_to');
        $this->actingAs($user)->get(route('my-work.timesheet', ['date_from' => '2026-08-16', 'date_to' => '2026-08-01']))->assertSessionHasErrors('date_to');
    }

    public function test_inconsistent_project_task_and_task_subtask_filters_are_rejected(): void
    {
        [$user, $project, $task] = $this->context();
        [, $otherProject, $otherTask, $otherSubtask] = $this->context(true, $user);
        $this->actingAs($user)->get(route('my-work.timesheet', ['project_id' => $project->id, 'task_id' => $otherTask->id]))->assertSessionHasErrors('task_id');
        $this->actingAs($user)->get(route('my-work.timesheet', ['task_id' => $task->id, 'subtask_id' => $otherSubtask->id]))->assertSessionHasErrors('subtask_id');
    }

    public function test_pagination_preserves_filters(): void
    {
        [$user, $project, $task] = $this->context();
        foreach (range(1, 16) as $number) {
            $this->entry($user, $project, $task, ['entry_code' => 'WEN-PAGE-'.$number]);
        }
        $response = $this->actingAs($user)->get(route('my-work.timesheet', ['project_id' => $project->id]))->assertOk();
        $this->assertStringContainsString('project_id='.$project->id, $response->viewData('entries')->nextPageUrl());
    }

    private function context(bool $withSubtask = false, ?User $user = null): array
    {
        static $sequence = 0;
        $sequence++;
        $user ??= $this->user();
        $project = Project::create(['project_code' => 'PRJ-TS-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT), 'title' => 'Timesheet Project '.$sequence, 'project_category_id' => ProjectCategory::firstOrFail()->id, 'owner_id' => $user->id, 'created_by' => $user->id, 'start_date' => '2026-01-01', 'due_date' => '2026-12-31', 'scope' => 'university_wide', 'priority_level' => 'medium', 'progress_method' => 'manual', 'progress_percentage' => 0, 'status' => 'in_progress', 'is_active' => true]);
        $task = Task::create(['task_code' => 'TSK-TS-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT), 'project_id' => $project->id, 'title' => 'Timesheet Task '.$sequence, 'created_by' => $user->id, 'assigned_by' => $user->id, 'priority' => 'medium', 'status' => 'in_progress', 'progress_percentage' => 0, 'is_active' => true]);
        $subtask = $withSubtask ? Subtask::create(['subtask_code' => 'SUB-TS-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT), 'task_id' => $task->id, 'title' => 'Timesheet Subtask', 'created_by' => $user->id, 'assigned_to' => $user->id, 'priority' => 'medium', 'status' => 'in_progress', 'progress_percentage' => 0, 'is_active' => true]) : null;

        return [$user, $project, $task, $subtask];
    }

    private function entry(User $user, Project $project, Task $task, array $overrides = []): WorkEntry
    {
        static $sequence = 0;
        $sequence++;

        return WorkEntry::create(array_merge(['entry_code' => 'WEN-TS-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT), 'user_id' => $user->id, 'project_id' => $project->id, 'task_id' => $task->id, 'work_date' => today()->toDateString(), 'start_time' => '09:00', 'end_time' => '12:30', 'duration_minutes' => 210, 'work_description' => 'Recorded timesheet work activity.'], $overrides));
    }

    private function user(): User
    {
        $user = User::factory()->create(['account_status' => 'active']);
        $user->assignRole('Staff');

        return $user;
    }
}

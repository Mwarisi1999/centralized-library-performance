<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\StaffProfile;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskProgressService;
use Database\Seeders\ProjectCategorySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskExecutionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, ProjectCategorySeeder::class]);
    }

    public function test_assignee_can_start_task_and_activity_is_recorded(): void
    {
        [$task,$assignee] = $this->taskWithAssignee();
        $this->actingAs($assignee)->post(route('tasks.start', $task))->assertRedirect();
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'in_progress']);
        $this->assertDatabaseHas('task_activities', ['task_id' => $task->id, 'user_id' => $assignee->id, 'activity_type' => 'task_started']);
    }

    public function test_non_assignee_and_monitoring_viewer_cannot_execute(): void
    {
        [$task] = $this->taskWithAssignee();
        $viewer = $this->user('M&E Officer');
        $this->actingAs($viewer)->post(route('tasks.start', $task))->assertForbidden();
    }

    public function test_manual_progress_is_allowed_without_subtasks_and_rejected_with_one(): void
    {
        [$task,$assignee] = $this->taskWithAssignee(['status' => 'in_progress']);
        $this->actingAs($assignee)->post(route('tasks.progress', $task), ['progress_percentage' => 40, 'message' => 'Draft ready'])->assertRedirect();
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'progress_percentage' => 40]);
        $this->subtask($task, $assignee, 0);
        $this->actingAs($assignee)->post(route('tasks.progress', $task), ['progress_percentage' => 70])->assertSessionHasErrors('progress_percentage');
    }

    public function test_four_subtasks_average_to_62_5_and_cancelled_or_inactive_are_excluded(): void
    {
        [$task,$assignee] = $this->taskWithAssignee();
        foreach ([100, 100, 50, 0] as $progress) {
            $this->subtask($task, $assignee, $progress);
        }
        $this->subtask($task, $assignee, 100, ['status' => 'cancelled']);
        $this->subtask($task, $assignee, 100, ['is_active' => false]);
        app(TaskProgressService::class)->recalculate($task);
        $this->assertSame(62.5, (float) $task->fresh()->progress_percentage);
    }

    public function test_subtask_progress_recalculates_parent_and_completion_forces_100(): void
    {
        [$task,$assignee] = $this->taskWithAssignee();
        $subtask = $this->subtask($task, $assignee, 0);
        $this->actingAs($assignee)->patch(route('subtasks.progress', $subtask), ['progress_percentage' => 50])->assertRedirect();
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'in_progress', 'progress_percentage' => 50]);
        $this->actingAs($assignee)->post(route('subtasks.complete', $subtask))->assertRedirect();
        $this->assertDatabaseHas('subtasks', ['id' => $subtask->id, 'status' => 'completed', 'progress_percentage' => 100]);
        $this->assertDatabaseHas('task_activities', ['task_id' => $task->id, 'activity_type' => 'subtask_completed']);
    }

    public function test_task_only_submits_for_review_at_100_percent(): void
    {
        [$task,$assignee] = $this->taskWithAssignee(['status' => 'in_progress', 'progress_percentage' => 50]);
        $this->actingAs($assignee)->post(route('tasks.submit-review', $task))->assertSessionHasErrors('task');
        $task->update(['progress_percentage' => 100]);
        $this->actingAs($assignee)->post(route('tasks.submit-review', $task))->assertRedirect();
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'pending_review']);
    }

    public function test_intern_cannot_create_subtask_and_invalid_assignee_is_rejected(): void
    {
        [$task,$assignee] = $this->taskWithAssignee();
        $intern = $this->user('Intern');
        $outsider = $this->user('Staff');
        $this->actingAs($intern)->post(route('tasks.subtasks.store', $task), $this->subtaskPayload($outsider))->assertForbidden();
        $this->actingAs($assignee)->post(route('tasks.subtasks.store', $task), $this->subtaskPayload($outsider))->assertSessionHasErrors('assigned_to');
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['account_status' => 'active']);
        $user->assignRole($role);

        return $user;
    }

    private function taskWithAssignee(array $overrides = []): array
    {
        $owner = $this->user('Administrator');
        $assignee = $this->user('Staff');
        StaffProfile::create([
            'user_id' => $assignee->id,
            'staff_number' => 'WORK-'.$assignee->id,
            'supervisor_id' => $owner->id,
            'status' => 'active',
        ]);
        $category = ProjectCategory::firstOrFail();
        $project = Project::create(['project_code' => 'PRJ-WORK-0001', 'title' => 'Workflow Project', 'project_category_id' => $category->id, 'owner_id' => $owner->id, 'created_by' => $owner->id, 'start_date' => today()->toDateString(), 'due_date' => today()->addMonth()->toDateString(), 'scope' => 'university_wide', 'priority_level' => 'medium', 'progress_method' => 'manual', 'progress_percentage' => 0, 'status' => 'planned', 'is_active' => true]);
        foreach ([$owner, $assignee] as $user) {
            $project->projectMembers()->create(['user_id' => $user->id, 'joined_at' => now(), 'is_active' => true]);
        }
        $task = Task::create(array_merge(['task_code' => 'TSK-WORK-0001', 'project_id' => $project->id, 'title' => 'Workflow Task', 'created_by' => $owner->id, 'assigned_by' => $owner->id, 'priority' => 'medium', 'status' => 'not_started', 'progress_percentage' => 0, 'is_active' => true], $overrides));
        $task->assignees()->attach($assignee, ['assigned_at' => now(), 'is_active' => true]);

        return [$task, $assignee, $owner];
    }

    private function subtask(Task $task, User $assignee, float $progress, array $overrides = []): Subtask
    {
        static $sequence = 0;
        $sequence++;

        return $task->subtasks()->create(array_merge(['subtask_code' => 'SUB-TEST-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT), 'title' => 'Subtask '.$sequence, 'created_by' => $assignee->id, 'assigned_to' => $assignee->id, 'priority' => 'medium', 'status' => $progress === 100.0 ? 'completed' : 'not_started', 'progress_percentage' => $progress, 'completed_at' => $progress === 100.0 ? now() : null, 'is_active' => true], $overrides));
    }

    private function subtaskPayload(User $assignee): array
    {
        return ['title' => 'New Subtask', 'assigned_to' => $assignee->id, 'priority' => 'medium', 'status' => 'not_started', 'progress_percentage' => 0];
    }
}

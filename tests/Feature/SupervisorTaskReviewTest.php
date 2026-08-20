<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\StaffProfile;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\ProjectCategorySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupervisorTaskReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, ProjectCategorySeeder::class]);
    }

    public function test_submission_resolves_stored_supervisor_and_creates_review_snapshot(): void
    {
        [$task, $assignee, $supervisor] = $this->workflow();
        $this->actingAs($assignee)->post(route('tasks.submit-review', $task))->assertRedirect();
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'pending_review', 'reviewer_id' => $supervisor->id, 'submitted_by' => $assignee->id]);
        $this->assertDatabaseHas('task_reviews', ['task_id' => $task->id, 'reviewer_id' => $supervisor->id, 'status' => 'pending']);
    }

    public function test_submission_fails_without_supervisor_and_admin_is_not_fallback(): void
    {
        [$task, $assignee] = $this->workflow(false);
        $administrator = $this->user('Administrator');
        $this->actingAs($assignee)->post(route('tasks.submit-review', $task))->assertSessionHasErrors('task');
        $this->assertNull($task->fresh()->reviewer_id);
        $this->actingAs($administrator)->post(route('tasks.approve', $task))->assertForbidden();
    }

    public function test_only_snapshot_reviewer_sees_controls_and_can_approve(): void
    {
        [$task, $assignee, $supervisor] = $this->submittedWorkflow();
        $administrator = $this->user('Administrator');
        $this->actingAs($supervisor)->get(route('tasks.show', $task))->assertOk()->assertSee('Supervisor Review')->assertSee('Approve Task');
        $this->actingAs($assignee)->get(route('tasks.show', $task))->assertOk()->assertDontSee('Supervisor Review');
        $this->actingAs($administrator)->post(route('tasks.approve', $task))->assertForbidden();
        $this->actingAs($supervisor)->post(route('tasks.approve', $task), ['remark' => 'Approved output.'])->assertRedirect();
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'completed', 'progress_percentage' => 100, 'reviewed_by' => $supervisor->id]);
        $this->assertDatabaseHas('task_activities', ['task_id' => $task->id, 'activity_type' => 'task_approved']);
        $this->assertSame('completed', $task->project->fresh()->status);
    }

    public function test_return_requires_reason_and_preserves_progress_and_subtasks(): void
    {
        [$task, $assignee, $supervisor] = $this->submittedWorkflow(true);
        $this->actingAs($supervisor)->post(route('tasks.return-correction', $task))->assertSessionHasErrors('remark');
        $this->actingAs($supervisor)->post(route('tasks.return-correction', $task), ['remark' => 'Correct the statistics.'])->assertRedirect();
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'in_progress', 'progress_percentage' => 100]);
        $this->assertDatabaseMissing('subtasks', ['task_id' => $task->id, 'status' => 'not_started']);
        $this->assertSame('in_progress', $task->project->fresh()->status);
        $this->assertNull($task->project->fresh()->completed_at);
    }

    public function test_returned_task_can_be_resubmitted_and_history_preserves_both_cycles(): void
    {
        [$task, $assignee, $supervisor] = $this->submittedWorkflow();
        $this->actingAs($supervisor)->post(route('tasks.return-correction', $task), ['remark' => 'Revise the final copy.']);
        $this->actingAs($assignee)->post(route('tasks.submit-review', $task->fresh()))->assertRedirect();
        $this->assertSame(2, $task->reviews()->count());
        $this->assertSame(['returned', 'pending'], $task->reviews()->orderBy('id')->pluck('status')->all());
        $this->actingAs($assignee)->post(route('tasks.submit-review', $task->fresh()))->assertSessionHasErrors('task');
    }

    public function test_review_queue_only_contains_tasks_for_authenticated_reviewer(): void
    {
        [$mine, , $supervisor] = $this->submittedWorkflow();
        [$other, , $otherSupervisor] = $this->submittedWorkflow();
        $mineResponse = $this->actingAs($supervisor)->get(route('tasks.index'))->assertOk()->assertSee('Tasks Awaiting My Review');
        $this->assertEquals([$mine->id], $mineResponse->viewData('reviewQueue')->pluck('id')->all());
        $otherResponse = $this->actingAs($otherSupervisor)->get(route('tasks.index'))->assertOk();
        $this->assertEquals([$other->id], $otherResponse->viewData('reviewQueue')->pluck('id')->all());
    }

    public function test_completed_task_cannot_be_submitted_again(): void
    {
        [$task, $assignee, $supervisor] = $this->submittedWorkflow();
        $this->actingAs($supervisor)->post(route('tasks.approve', $task));
        $this->actingAs($assignee)->post(route('tasks.submit-review', $task->fresh()))->assertSessionHasErrors('task');
    }

    private function submittedWorkflow(bool $withSubtask = false): array
    {
        [$task, $assignee, $supervisor] = $this->workflow(true, $withSubtask);
        $this->actingAs($assignee)->post(route('tasks.submit-review', $task));

        return [$task->fresh(), $assignee, $supervisor];
    }

    private function workflow(bool $withSupervisor = true, bool $withSubtask = false): array
    {
        static $sequence = 0;
        $sequence++;
        $owner = $this->user('Administrator');
        $assignee = $this->user('Staff');
        $supervisor = $this->user('Campus Librarian');
        StaffProfile::create(['user_id' => $assignee->id, 'staff_number' => 'REV-A-'.$sequence, 'supervisor_id' => $withSupervisor ? $supervisor->id : null, 'status' => 'active']);
        StaffProfile::create(['user_id' => $supervisor->id, 'staff_number' => 'REV-S-'.$sequence, 'status' => 'active']);
        $project = Project::create(['project_code' => 'PRJ-REV-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT), 'title' => 'Review Project', 'project_category_id' => ProjectCategory::firstOrFail()->id, 'owner_id' => $owner->id, 'created_by' => $owner->id, 'start_date' => '2026-01-01', 'due_date' => '2026-12-31', 'scope' => 'university_wide', 'priority_level' => 'medium', 'progress_method' => 'tasks', 'progress_percentage' => 0, 'status' => 'planned', 'is_active' => true]);
        $project->members()->attach([$assignee->id => ['joined_at' => now(), 'is_active' => true], $supervisor->id => ['joined_at' => now(), 'is_active' => true]]);
        $task = Task::create(['task_code' => 'TSK-REV-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT), 'project_id' => $project->id, 'title' => 'Review Task '.$sequence, 'created_by' => $owner->id, 'assigned_by' => $owner->id, 'priority' => 'medium', 'status' => 'in_progress', 'progress_percentage' => 100, 'is_active' => true]);
        $task->assignees()->attach($assignee, ['assigned_at' => now(), 'is_active' => true]);
        if ($withSubtask) {
            Subtask::create(['subtask_code' => 'SUB-REV-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT), 'task_id' => $task->id, 'title' => 'Completed Subtask', 'created_by' => $owner->id, 'assigned_to' => $assignee->id, 'priority' => 'medium', 'status' => 'completed', 'progress_percentage' => 100, 'completed_at' => now(), 'is_active' => true]);
        }

        return [$task, $assignee, $supervisor];
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['account_status' => 'active']);
        $user->assignRole($role);

        return $user;
    }
}

<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\User;
use App\Services\ProjectProgressService;
use App\Services\TaskProgressService;
use Database\Seeders\ProjectCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectProgressRollupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ProjectCategorySeeder::class);
    }

    public function test_task_based_project_without_tasks_is_zero_and_not_completed(): void
    {
        $project = $this->project('tasks', ['status' => 'planned', 'progress_percentage' => 75]);
        app(ProjectProgressService::class)->recalculate($project);
        $this->assertSame(0.0, (float) $project->fresh()->progress_percentage);
        $this->assertSame('planned', $project->fresh()->status);
    }

    public function test_one_task_rolls_up_to_fifty_percent_and_starts_project(): void
    {
        $project = $this->project('tasks');
        $this->task($project, 50, 'in_progress');
        $this->assertSame(50.0, (float) $project->fresh()->progress_percentage);
        $this->assertSame('in_progress', $project->fresh()->status);
    }

    public function test_all_not_started_tasks_leave_a_planned_project_planned(): void
    {
        $project = $this->project('tasks');
        $this->task($project, 0, 'not_started');
        $this->task($project, 0, 'not_started');

        $this->assertSame('planned', $project->fresh()->status);
        $this->assertSame(0.0, (float) $project->fresh()->progress_percentage);
    }

    public function test_task_start_and_progress_update_recalculate_only_its_project(): void
    {
        $project = $this->project('tasks');
        $otherProject = $this->project('tasks');
        $task = $this->task($project, 0, 'not_started');
        $this->task($project, 0, 'not_started');
        $this->task($otherProject, 40, 'in_progress');
        $otherBefore = $otherProject->fresh()->only(['status', 'progress_percentage', 'updated_at']);

        $task->update(['status' => 'in_progress', 'progress_percentage' => 25]);

        $this->assertSame('in_progress', $project->fresh()->status);
        $this->assertSame(12.5, (float) $project->fresh()->progress_percentage);
        $this->assertEquals($otherBefore, $otherProject->fresh()->only(['status', 'progress_percentage', 'updated_at']));
    }

    public function test_equal_weighting_produces_43_75_and_excludes_cancelled_or_inactive_tasks(): void
    {
        $project = $this->project('tasks');
        foreach ([100, 50, 25, 0] as $progress) {
            $this->task($project, $progress, $progress > 0 ? 'in_progress' : 'not_started');
        }
        $this->task($project, 100, 'cancelled');
        $this->task($project, 100, 'completed', ['is_active' => false]);
        $this->assertSame(43.75, (float) $project->fresh()->progress_percentage);
    }

    public function test_pending_review_at_one_hundred_keeps_project_in_progress(): void
    {
        $project = $this->project('tasks');
        $this->task($project, 100, 'pending_review');
        $this->assertSame(100.0, (float) $project->fresh()->progress_percentage);
        $this->assertSame('in_progress', $project->fresh()->status);
        $this->assertNull($project->fresh()->completed_at);
    }

    public function test_only_all_finally_completed_tasks_complete_project(): void
    {
        $project = $this->project('tasks');
        $first = $this->task($project, 100, 'completed');
        $second = $this->task($project, 100, 'pending_review');
        $this->assertSame('in_progress', $project->fresh()->status);
        $second->update(['status' => 'completed', 'completed_at' => now()]);
        $this->assertSame('completed', $project->fresh()->status);
        $this->assertNotNull($project->fresh()->completed_at);
        $first->update(['status' => 'in_progress', 'progress_percentage' => 50, 'completed_at' => null]);
        $this->assertSame('in_progress', $project->fresh()->status);
        $this->assertNull($project->fresh()->completed_at);
    }

    public function test_subtask_rollup_flows_through_task_to_project(): void
    {
        $project = $this->project('tasks');
        $task = $this->task($project, 0, 'not_started');
        $this->subtask($task, 100);
        $this->subtask($task, 50);
        app(TaskProgressService::class)->recalculate($task);
        $this->assertSame(75.0, (float) $task->fresh()->progress_percentage);
        $this->assertSame(75.0, (float) $project->fresh()->progress_percentage);
        $this->assertSame('in_progress', $project->fresh()->status);
    }

    public function test_manual_project_is_never_overwritten_by_task_changes(): void
    {
        $project = $this->project('manual', ['progress_percentage' => 37, 'status' => 'planned']);
        $task = $this->task($project, 50, 'in_progress');
        $task->update(['progress_percentage' => 90]);
        $this->assertSame(37.0, (float) $project->fresh()->progress_percentage);
        $this->assertSame('planned', $project->fresh()->status);
    }

    public function test_cancelled_or_inactive_project_is_not_reopened_by_task_changes(): void
    {
        foreach ([['cancelled', true], ['planned', false]] as [$status, $isActive]) {
            $project = $this->project('tasks', ['status' => $status, 'is_active' => $isActive, 'progress_percentage' => 30]);
            $task = $this->task($project, 25, 'in_progress');
            $task->update(['progress_percentage' => 75]);

            $this->assertSame($status, $project->fresh()->status);
            $this->assertSame(30.0, (float) $project->fresh()->progress_percentage);
        }
    }

    public function test_switching_to_tasks_recalculates_and_switching_to_manual_preserves_value(): void
    {
        $project = $this->project('manual', ['progress_percentage' => 10]);
        $this->task($project, 60, 'in_progress');
        $project->update(['progress_method' => 'tasks']);
        app(ProjectProgressService::class)->recalculate($project);
        $this->assertSame(60.0, (float) $project->fresh()->progress_percentage);
        $project->update(['progress_method' => 'manual']);
        app(ProjectProgressService::class)->recalculate($project);
        $this->assertSame(60.0, (float) $project->fresh()->progress_percentage);
    }

    private function project(string $method, array $overrides = []): Project
    {
        static $sequence = 0;
        $sequence++;
        $owner = User::factory()->create(['account_status' => 'active']);

        return Project::create(array_merge([
            'project_code' => 'PRJ-ROLL-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
            'title' => 'Rollup Project '.$sequence,
            'project_category_id' => ProjectCategory::firstOrFail()->id,
            'owner_id' => $owner->id,
            'created_by' => $owner->id,
            'start_date' => '2026-01-01',
            'due_date' => '2026-12-31',
            'scope' => 'university_wide',
            'priority_level' => 'medium',
            'progress_method' => $method,
            'progress_percentage' => 0,
            'status' => 'planned',
            'is_active' => true,
        ], $overrides));
    }

    private function task(Project $project, float $progress, string $status, array $overrides = []): Task
    {
        static $sequence = 0;
        $sequence++;

        return Task::create(array_merge([
            'task_code' => 'TSK-ROLL-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
            'project_id' => $project->id,
            'title' => 'Rollup Task '.$sequence,
            'created_by' => $project->owner_id,
            'assigned_by' => $project->owner_id,
            'priority' => 'medium',
            'status' => $status,
            'progress_percentage' => $progress,
            'is_active' => true,
        ], $overrides));
    }

    private function subtask(Task $task, float $progress): Subtask
    {
        static $sequence = 0;
        $sequence++;

        return $task->subtasks()->create([
            'subtask_code' => 'SUB-ROLL-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
            'title' => 'Rollup Subtask '.$sequence,
            'created_by' => $task->created_by,
            'priority' => 'medium',
            'status' => $progress === 100.0 ? 'completed' : 'in_progress',
            'progress_percentage' => $progress,
            'is_active' => true,
        ]);
    }
}

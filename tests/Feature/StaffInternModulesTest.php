<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkEntry;
use Database\Seeders\ProjectCategorySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffInternModulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, ProjectCategorySeeder::class]);
    }

    public function test_all_operational_roles_can_open_all_four_personal_work_modules(): void
    {
        foreach (['Staff', 'Intern', 'M&E Officer', 'Campus Librarian', 'University Librarian', 'Administrator'] as $role) {
            $user = $this->user($role);

            foreach (['daily-activities.index', 'weekly-activities.index', 'task-tracker.index', 'printable-timesheet.index'] as $route) {
                $this->actingAs($user)->get(route($route))->assertOk();
            }
        }
    }

    public function test_active_user_without_required_capabilities_cannot_open_personal_work_modules(): void
    {
        $user = User::factory()->create(['account_status' => 'active']);

        foreach (['daily-activities.index', 'weekly-activities.index', 'task-tracker.index', 'printable-timesheet.index'] as $route) {
            $this->actingAs($user)->get(route($route))->assertForbidden();
        }
    }

    public function test_personal_work_modules_remain_scoped_to_the_authenticated_user(): void
    {
        $viewer = $this->user('M&E Officer');
        $other = $this->user('Staff');
        [$viewerProject, $viewerTask] = $this->assignment($viewer, 'VIEWER');
        [$otherProject, $otherTask] = $this->assignment($other, 'OTHER');
        WorkEntry::create([
            'entry_code' => 'WEN-VIEWER-0001', 'user_id' => $viewer->id,
            'project_id' => $viewerProject->id, 'task_id' => $viewerTask->id,
            'work_date' => '2026-08-12', 'priority' => 'medium', 'activity_status' => 'completed',
            'start_time' => '09:00', 'end_time' => '10:00', 'duration_minutes' => 60,
            'work_description' => 'Viewer personal activity.',
        ]);
        WorkEntry::create([
            'entry_code' => 'WEN-OTHER-0001', 'user_id' => $other->id,
            'project_id' => $otherProject->id, 'task_id' => $otherTask->id,
            'work_date' => '2026-08-12', 'priority' => 'medium', 'activity_status' => 'completed',
            'start_time' => '10:00', 'end_time' => '11:00', 'duration_minutes' => 60,
            'work_description' => 'Other user private activity.',
        ]);

        foreach (['daily-activities.index', 'weekly-activities.index', 'printable-timesheet.index'] as $route) {
            $this->actingAs($viewer)->get(route($route, ['month' => 8, 'year' => 2026, 'week' => 3]))
                ->assertOk()
                ->assertSee('Viewer personal activity.')
                ->assertDontSee('Other user private activity.');
        }

        $this->actingAs($viewer)->get(route('task-tracker.index'))
            ->assertOk()
            ->assertSee($viewerTask->task_code)
            ->assertDontSee($otherTask->task_code);
    }

    public function test_one_activity_feeds_daily_weekly_tracker_and_printable_timesheet(): void
    {
        $this->travelTo('2026-08-12 17:00:00');
        $user = $this->user('Staff');
        [$project, $task] = $this->assignment($user);
        WorkEntry::create([
            'entry_code' => 'WEN-MODULE-0001', 'user_id' => $user->id,
            'project_id' => $project->id, 'task_id' => $task->id,
            'work_date' => '2026-08-12', 'due_date' => '2026-08-14',
            'priority' => 'high', 'activity_status' => 'completed',
            'start_time' => '09:00', 'end_time' => '12:00', 'duration_minutes' => 180,
            'work_description' => 'Completed module integration activity.',
            'output_deliverable' => 'Integrated working module.',
        ]);

        $this->actingAs($user)->get(route('daily-activities.index'))->assertSee('WEN-MODULE-0001');
        $this->actingAs($user)->get(route('weekly-activities.index', ['month' => 8, 'year' => 2026, 'week' => 3]))->assertSee('Completed module integration activity.');
        $this->actingAs($user)->get(route('task-tracker.index'))->assertSee($task->task_code)->assertSee('3 hours');
        $this->actingAs($user)->get(route('printable-timesheet.index', ['month' => 8, 'year' => 2026]))->assertSee('Integrated working module.');
    }

    public function test_weekly_calendar_recalculates_weeks_for_the_selected_month(): void
    {
        $user = $this->user('Staff');

        $this->actingAs($user)
            ->get(route('weekly-activities.index', ['month' => 3, 'year' => 2026, 'week' => 6]))
            ->assertOk()
            ->assertSee('30 Mar – 5 Apr 2026')
            ->assertSee('data-weekly-day-open', false)
            ->assertSee('No activity');
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['account_status' => 'active']);
        $user->assignRole($role);

        return $user;
    }

    private function assignment(User $user, string $suffix = 'MODULE'): array
    {
        $project = Project::create([
            'project_code' => "PRJ-{$suffix}-0001", 'title' => "{$suffix} Project",
            'project_category_id' => ProjectCategory::firstOrFail()->id,
            'owner_id' => $user->id, 'created_by' => $user->id,
            'start_date' => '2026-08-01', 'due_date' => '2026-08-31',
            'scope' => 'university_wide', 'priority_level' => 'medium',
            'progress_method' => 'manual', 'progress_percentage' => 50,
            'status' => 'in_progress', 'is_active' => true,
        ]);
        $project->members()->attach($user, ['joined_at' => now(), 'is_active' => true]);
        $task = Task::create([
            'task_code' => "TSK-{$suffix}-0001", 'project_id' => $project->id,
            'title' => "{$suffix} Task", 'created_by' => $user->id, 'assigned_by' => $user->id,
            'start_date' => '2026-08-01', 'due_date' => '2026-08-20',
            'priority' => 'high', 'status' => 'in_progress',
            'progress_percentage' => 50, 'is_active' => true,
        ]);
        $task->assignees()->attach($user, ['assigned_at' => now(), 'is_active' => true]);

        return [$project, $task];
    }
}

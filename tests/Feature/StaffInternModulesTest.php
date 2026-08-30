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

    public function test_staff_and_intern_can_open_all_four_operational_modules(): void
    {
        foreach (['Staff', 'Intern'] as $role) {
            $user = $this->user($role);

            foreach (['daily-activities.index', 'weekly-activities.index', 'task-tracker.index', 'printable-timesheet.index'] as $route) {
                $this->actingAs($user)->get(route($route))->assertOk();
            }
        }
    }

    public function test_non_operational_role_cannot_open_staff_and_intern_modules(): void
    {
        $viewer = $this->user('M&E Officer');

        foreach (['daily-activities.index', 'weekly-activities.index', 'task-tracker.index', 'printable-timesheet.index'] as $route) {
            $this->actingAs($viewer)->get(route($route))->assertForbidden();
        }
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

    private function assignment(User $user): array
    {
        $project = Project::create([
            'project_code' => 'PRJ-MODULE-0001', 'title' => 'Module Project',
            'project_category_id' => ProjectCategory::firstOrFail()->id,
            'owner_id' => $user->id, 'created_by' => $user->id,
            'start_date' => '2026-08-01', 'due_date' => '2026-08-31',
            'scope' => 'university_wide', 'priority_level' => 'medium',
            'progress_method' => 'manual', 'progress_percentage' => 50,
            'status' => 'in_progress', 'is_active' => true,
        ]);
        $project->members()->attach($user, ['joined_at' => now(), 'is_active' => true]);
        $task = Task::create([
            'task_code' => 'TSK-MODULE-0001', 'project_id' => $project->id,
            'title' => 'Module Task', 'created_by' => $user->id, 'assigned_by' => $user->id,
            'start_date' => '2026-08-01', 'due_date' => '2026-08-20',
            'priority' => 'high', 'status' => 'in_progress',
            'progress_percentage' => 50, 'is_active' => true,
        ]);
        $task->assignees()->attach($user, ['assigned_at' => now(), 'is_active' => true]);

        return [$project, $task];
    }
}

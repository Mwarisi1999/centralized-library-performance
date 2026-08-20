<?php

namespace Tests\Feature;

use App\Models\Campus;
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

class CampusDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, ProjectCategorySeeder::class]);
        $this->travelTo('2026-08-19 12:00:00');
    }

    public function test_only_campus_librarians_can_access_the_additional_dashboard(): void
    {
        $campus = $this->campus('MAIN');
        $librarian = $this->staff($campus, 'Campus Librarian', 'LIB-1');

        $this->actingAs($librarian)->get(route('campus-dashboard.index'))->assertOk()->assertSee('Main Campus Dashboard');
        $this->actingAs($this->staff($campus, 'Staff', 'STAFF-1'))->get(route('campus-dashboard.index'))->assertForbidden();
        $this->actingAs($this->staff($campus, 'Intern', 'INTERN-1'))->get(route('campus-dashboard.index'))->assertForbidden();
        $this->actingAs($this->staff($campus, 'Administrator', 'ADMIN-1'))->get(route('campus-dashboard.index'))->assertForbidden();
    }

    public function test_dashboard_is_campus_scoped_and_loading_it_is_read_only(): void
    {
        $main = $this->campus('MAIN');
        $other = $this->campus('OTHER');
        $librarian = $this->staff($main, 'Campus Librarian', 'LIB-2');
        $mainStaff = $this->staff($main, 'Staff', 'STAFF-2', 'Main Campus Staff');
        $otherStaff = $this->staff($other, 'Staff', 'STAFF-3', 'Other Campus Staff');
        [$mainProject, $mainTask] = $this->workContext($mainStaff, 'MAIN');
        [$otherProject, $otherTask] = $this->workContext($otherStaff, 'OTHER');
        $this->entry($mainStaff, $mainProject, $mainTask, 120);
        $this->entry($otherStaff, $otherProject, $otherTask, 600);
        MonthlyReport::create(['report_code' => 'MR-202608-0001', 'user_id' => $otherStaff->id, 'reporting_month' => 8, 'reporting_year' => 2026, 'status' => 'pending_review']);
        $before = [WorkEntry::count(), MonthlyReport::count()];

        $response = $this->actingAs($librarian)->get(route('campus-dashboard.index'))
            ->assertOk()->assertSee('Main Campus Staff')->assertDontSee('Other Campus Staff');
        $summary = $response->viewData('summary');

        $this->assertSame(2, $summary['total_staff']);
        $this->assertSame(120, $summary['minutes']);
        $this->assertSame(1, $summary['active_tasks']);
        $this->assertSame(0, $summary['reports']->get('pending_review'));
        $this->assertSame($before, [WorkEntry::count(), MonthlyReport::count()]);
    }

    public function test_period_filter_and_staff_drilldown_enforce_campus_scope(): void
    {
        $main = $this->campus('MAIN');
        $other = $this->campus('OTHER');
        $librarian = $this->staff($main, 'Campus Librarian', 'LIB-3');
        $mainStaff = $this->staff($main, 'Staff', 'STAFF-4', 'Visible Staff');
        $otherStaff = $this->staff($other, 'Staff', 'STAFF-5', 'Hidden Staff');

        $this->actingAs($librarian)->get(route('campus-dashboard.staff', ['staff' => $mainStaff, 'month' => 7, 'year' => 2026]))
            ->assertOk()->assertSee('July 2026')->assertSee('Visible Staff');
        $this->actingAs($librarian)->get(route('campus-dashboard.staff', $otherStaff))->assertForbidden();
        $this->actingAs($librarian)->get(route('campus-dashboard.index', ['month' => 13, 'year' => 2026]))->assertSessionHasErrors('month');
    }

    private function campus(string $code): Campus
    {
        return Campus::create(['code' => $code, 'name' => $code === 'MAIN' ? 'Main Campus' : 'Other Campus', 'is_active' => true]);
    }

    private function staff(Campus $campus, string $role, string $number, ?string $name = null): User
    {
        $user = User::factory()->create(['name' => $name ?? $number, 'account_status' => 'active']);
        $user->assignRole($role);
        StaffProfile::create(['user_id' => $user->id, 'staff_number' => $number, 'campus_id' => $campus->id, 'status' => 'active']);

        return $user;
    }

    private function workContext(User $user, string $suffix): array
    {
        $project = Project::create(['project_code' => 'PRJ-'.$suffix, 'title' => $suffix.' Project', 'project_category_id' => ProjectCategory::firstOrFail()->id, 'owner_id' => $user->id, 'created_by' => $user->id, 'start_date' => '2026-08-01', 'due_date' => '2026-09-01', 'scope' => 'selected_campuses', 'priority_level' => 'medium', 'progress_method' => 'tasks', 'progress_percentage' => 25, 'status' => 'in_progress', 'is_active' => true]);
        $project->campuses()->attach($user->staffProfile->campus_id);
        $task = Task::create(['task_code' => 'TSK-'.$suffix, 'project_id' => $project->id, 'title' => $suffix.' Task', 'created_by' => $user->id, 'assigned_by' => $user->id, 'due_date' => '2026-08-25', 'priority' => 'medium', 'status' => 'in_progress', 'progress_percentage' => 25, 'is_active' => true]);
        $task->taskAssignees()->create(['user_id' => $user->id, 'assigned_at' => '2026-08-01', 'is_active' => true]);

        return [$project, $task];
    }

    private function entry(User $user, Project $project, Task $task, int $minutes): void
    {
        WorkEntry::create(['entry_code' => 'ENT-'.$user->id, 'user_id' => $user->id, 'project_id' => $project->id, 'task_id' => $task->id, 'work_date' => '2026-08-10', 'start_time' => '08:00', 'end_time' => '10:00', 'duration_minutes' => $minutes, 'work_description' => 'Campus dashboard test work.']);
    }
}

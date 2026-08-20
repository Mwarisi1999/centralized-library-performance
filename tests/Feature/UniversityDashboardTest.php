<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\CampusMonthlyReport;
use App\Models\Library;
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

class UniversityDashboardTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, ProjectCategorySeeder::class]);
        $this->travelTo('2026-08-19 12:00:00');
    }

    public function test_only_active_university_librarian_can_access_dashboard_and_navigation(): void
    {
        $campus = $this->campus('MAIN');
        $universityLibrarian = $this->staff($campus, 'University Librarian');

        $this->actingAs($universityLibrarian)->get(route('university-dashboard.index'))->assertOk()
            ->assertSee('University Librarian Dashboard')->assertSee('University Dashboard');

        foreach (['Campus Librarian', 'Staff', 'Intern', 'Administrator', 'M&E Officer'] as $role) {
            $this->actingAs($this->staff($campus, $role))->get(route('university-dashboard.index'))->assertForbidden();
        }

        $universityLibrarian->update(['account_status' => 'inactive']);
        $this->actingAs($universityLibrarian)->get(route('university-dashboard.index'))->assertForbidden();
    }

    public function test_period_filter_aggregates_campuses_while_preserving_separate_rows(): void
    {
        $main = $this->campus('MAIN');
        $other = $this->campus('OTHER');
        Library::create(['campus_id' => $main->id, 'name' => 'Main Library', 'code' => 'ML', 'is_active' => true]);
        Library::create(['campus_id' => $other->id, 'name' => 'Other Library', 'code' => 'OL', 'is_active' => true]);
        $viewer = $this->staff($main, 'University Librarian');
        $mainStaff = $this->staff($main, 'Staff', 'Main Staff');
        $otherStaff = $this->staff($other, 'Staff', 'Other Staff');
        [$mainProject, $mainTask] = $this->context($mainStaff, 'completed', 'MAIN');
        [$otherProject, $otherTask] = $this->context($otherStaff, 'in_progress', 'OTHER');
        $this->entry($mainStaff, $mainProject, $mainTask, '2026-08-08', 60);
        $this->entry($otherStaff, $otherProject, $otherTask, '2026-08-09', 120);
        $this->entry($mainStaff, $mainProject, $mainTask, '2026-07-08', 600);

        $response = $this->actingAs($viewer)->get(route('university-dashboard.index', ['month' => 8, 'year' => 2026]))->assertOk();
        $summary = $response->viewData('summary');
        $rows = $response->viewData('campusRows')->keyBy('campus.code');

        $this->assertSame(2, $summary['total_campuses']);
        $this->assertSame(2, $summary['total_libraries']);
        $this->assertSame(180, $summary['minutes']);
        $this->assertSame(2, $summary['active_tasks']);
        $this->assertSame(1, $summary['completed_tasks']);
        $this->assertSame(60, $rows['MAIN']['minutes']);
        $this->assertSame(120, $rows['OTHER']['minutes']);
        $response->assertSee('August 2026')->assertSee('Main Staff')->assertSee('Other Staff');
        $this->actingAs($viewer)->get(route('university-dashboard.index', ['month' => 13, 'year' => 2026]))->assertSessionHasErrors('month');
    }

    public function test_university_librarian_can_drill_into_any_active_campus_without_weakening_campus_scope(): void
    {
        $main = $this->campus('MAIN');
        $other = $this->campus('OTHER');
        $viewer = $this->staff($main, 'University Librarian');
        $mainLibrarian = $this->staff($main, 'Campus Librarian');
        $otherStaff = $this->staff($other, 'Staff', 'Other Campus Person');

        $this->actingAs($viewer)->get(route('university-dashboard.campus', ['campus' => $other, 'month' => 8, 'year' => 2026]))
            ->assertOk()->assertSee('Other Campus Person')->assertSee('Read-only oversight');
        $this->actingAs($mainLibrarian)->get(route('university-dashboard.campus', $other))->assertForbidden();
        $this->actingAs($mainLibrarian)->get(route('campus-dashboard.staff', $otherStaff))->assertForbidden();
    }

    public function test_reporting_status_and_frozen_snapshot_access_are_read_only(): void
    {
        $main = $this->campus('MAIN');
        $other = $this->campus('OTHER');
        $viewer = $this->staff($main, 'University Librarian');
        $finalizer = $this->staff($other, 'Campus Librarian');
        $report = CampusMonthlyReport::create([
            'report_code' => 'CMR-2026-0001', 'campus_id' => $other->id, 'reporting_month' => 8,
            'reporting_year' => 2026, 'status' => 'finalized', 'finalized_by' => $finalizer->id,
            'finalized_at' => now(), 'snapshot' => $this->snapshot('Frozen campus result'),
        ]);

        $dashboard = $this->actingAs($viewer)->get(route('university-dashboard.index', ['month' => 8, 'year' => 2026]))->assertOk();
        $this->assertSame(1, $dashboard->viewData('summary')['reports_finalized']);
        $this->assertSame(1, $dashboard->viewData('summary')['reports_outstanding']);
        $dashboard->assertSee('CMR-2026-0001')->assertSee('Not Finalized');
        $this->actingAs($viewer)->get(route('campus-reports.show', $report))->assertOk()->assertSee('Frozen campus result');
        $this->actingAs($viewer)->post(route('campus-reports.finalize'), ['month' => 8, 'year' => 2026])->assertForbidden();
        $this->assertSame('Frozen campus result', $report->fresh()->snapshot['narrative']['achievements'][0]['text']);
    }

    private function campus(string $code): Campus
    {
        return Campus::create(['code' => $code, 'name' => $code.' Campus', 'is_active' => true]);
    }

    private function staff(Campus $campus, string $role, ?string $name = null): User
    {
        $user = User::factory()->create(['name' => $name ?? $role.' '.++$this->sequence, 'account_status' => 'active']);
        $user->assignRole($role);
        StaffProfile::create(['user_id' => $user->id, 'staff_number' => 'S-'.++$this->sequence, 'campus_id' => $campus->id, 'status' => 'active']);

        return $user;
    }

    private function context(User $user, string $status, string $suffix): array
    {
        $project = Project::create(['project_code' => 'PRJ-'.$suffix, 'title' => $suffix.' Project', 'project_category_id' => ProjectCategory::firstOrFail()->id, 'owner_id' => $user->id, 'created_by' => $user->id, 'start_date' => '2026-08-01', 'due_date' => '2026-09-01', 'scope' => 'selected_campuses', 'priority_level' => 'medium', 'progress_method' => 'tasks', 'progress_percentage' => 50, 'status' => 'in_progress', 'is_active' => true]);
        $project->campuses()->attach($user->staffProfile->campus_id);
        $task = Task::create(['task_code' => 'TSK-'.$suffix, 'project_id' => $project->id, 'title' => $suffix.' Task', 'created_by' => $user->id, 'assigned_by' => $user->id, 'due_date' => '2026-08-25', 'priority' => 'medium', 'status' => $status, 'progress_percentage' => $status === 'completed' ? 100 : 50, 'is_active' => true]);
        $task->taskAssignees()->create(['user_id' => $user->id, 'assigned_at' => '2026-08-01', 'is_active' => true]);

        return [$project, $task];
    }

    private function entry(User $user, Project $project, Task $task, string $date, int $minutes): void
    {
        WorkEntry::create(['entry_code' => 'ENT-'.++$this->sequence, 'user_id' => $user->id, 'project_id' => $project->id, 'task_id' => $task->id, 'work_date' => $date, 'start_time' => '08:00', 'end_time' => '10:00', 'duration_minutes' => $minutes, 'work_description' => 'University dashboard test.']);
    }

    private function snapshot(string $achievement): array
    {
        return ['identity' => ['campus' => 'OTHER Campus', 'period' => 'August 2026', 'month' => 8, 'year' => 2026], 'campus' => ['name' => 'OTHER Campus', 'campus_librarian' => 'Campus Librarian', 'libraries' => 0, 'total_staff' => 1, 'active_staff' => 1], 'performance' => ['total_hours' => '1h', 'staff_reporting' => 1, 'tasks_assigned' => 0, 'tasks_completed' => 0, 'tasks_in_progress' => 0, 'pending_review' => 0, 'overdue' => 0, 'completion_rate' => 0, 'active_projects' => 0, 'average_project_progress' => 0], 'staff_rows' => [], 'project_rows' => [], 'task_status' => [], 'report_status' => [], 'narrative' => ['achievements' => [['text' => $achievement, 'staff' => 'Staff']], 'challenges' => [], 'corrective_actions' => [], 'support_required' => [], 'planned_activities' => []]];
    }
}

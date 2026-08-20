<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\CampusMonthlyReport;
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

class CampusMonthlyReportTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, ProjectCategorySeeder::class]);
        $this->travelTo('2026-08-19 12:00:00');
    }

    public function test_only_active_campus_librarian_with_permission_can_access_report(): void
    {
        $campus = $this->campus('MAIN');
        $librarian = $this->staff($campus, 'Campus Librarian');

        $this->actingAs($librarian)->get(route('campus-reports.create'))->assertOk()->assertSee('Campus Consolidated Monthly Report');
        foreach (['Staff', 'Intern', 'Administrator', 'University Librarian'] as $role) {
            $this->actingAs($this->staff($campus, $role))->get(route('campus-reports.create'))->assertForbidden();
        }

        $librarian->staffProfile->update(['status' => 'inactive']);
        $this->actingAs($librarian)->get(route('campus-reports.index'))->assertForbidden();
    }

    public function test_period_defaults_validates_and_get_is_read_only(): void
    {
        $librarian = $this->staff($this->campus('MAIN'), 'Campus Librarian');

        $response = $this->actingAs($librarian)->get(route('campus-reports.create'))->assertOk();
        $this->assertSame('August 2026', $response->viewData('data')['identity']['period']);
        $this->assertDatabaseCount('campus_monthly_reports', 0);
        $this->assertDatabaseCount('monthly_reports', 0);
        $this->actingAs($librarian)->get(route('campus-reports.create', ['month' => 13, 'year' => 2026]))->assertSessionHasErrors('month');
        $this->actingAs($librarian)->get(route('campus-reports.create', ['month' => 8, 'year' => 1999]))->assertSessionHasErrors('year');
    }

    public function test_metrics_staff_and_narratives_are_campus_and_period_scoped(): void
    {
        $main = $this->campus('MAIN');
        $other = $this->campus('OTHER');
        $librarian = $this->staff($main, 'Campus Librarian', 'Campus Librarian');
        $staff = $this->staff($main, 'Staff', 'Main Staff');
        $outsider = $this->staff($other, 'Staff', 'Other Staff');
        [$project, $completed] = $this->context($staff, 'completed', 100, '2026-08-02');
        [, $inProgress] = $this->context($staff, 'in_progress', 40, '2026-08-03', '2026-08-10');
        [$otherProject, $otherTask] = $this->context($outsider, 'completed', 100, '2026-08-02');
        $this->entry($staff, $project, $completed, '2026-08-05', 90, ['output_deliverable' => 'Cataloguing completed.', 'challenge_encountered' => 'Network outage.', 'corrective_action' => 'Used offline forms.', 'support_required' => 'Backup router.', 'planned_next_activity' => 'Validate records.']);
        $this->entry($staff, $project, $completed, '2026-08-05', 30, ['output_deliverable' => ' Cataloguing completed. ']);
        $this->entry($staff, $project, $completed, '2026-07-05', 600, ['output_deliverable' => 'July output.']);
        $this->entry($outsider, $otherProject, $otherTask, '2026-08-05', 600, ['output_deliverable' => 'Other campus output.']);
        MonthlyReport::create(['report_code' => 'MRP-2026-0001', 'user_id' => $staff->id, 'reporting_month' => 8, 'reporting_year' => 2026, 'status' => 'approved']);

        $data = $this->actingAs($librarian)->get(route('campus-reports.create', ['month' => 8, 'year' => 2026]))->assertOk()->viewData('data');

        $this->assertSame(2, $data['campus']['total_staff']);
        $this->assertSame(120, $data['performance']['total_minutes']);
        $this->assertSame(2, $data['performance']['tasks_assigned']);
        $this->assertSame(1, $data['performance']['tasks_completed']);
        $this->assertSame(50.0, $data['performance']['completion_rate']);
        $this->assertSame(1, $data['performance']['overdue']);
        $this->assertSame(1, $data['report_status']['approved']);
        $this->assertSame(1, $data['report_status']['no_report']);
        $this->assertSame([['text' => 'Cataloguing completed.', 'staff' => 'Main Staff']], $data['narrative']['achievements']);
        $this->assertSame('Network outage.', $data['narrative']['challenges'][0]['text']);
        $this->assertSame('Used offline forms.', $data['narrative']['corrective_actions'][0]['text']);
        $this->assertSame('Backup router.', $data['narrative']['support_required'][0]['text']);
        $this->assertSame('Validate records.', $data['narrative']['planned_activities'][0]['text']);
        $this->assertNotContains('Other Staff', array_column($data['staff_rows'], 'name'));
        $this->assertDatabaseCount('monthly_reports', 1);
    }

    public function test_finalization_is_idempotent_stable_and_frozen(): void
    {
        $campus = $this->campus('MAIN');
        $librarian = $this->staff($campus, 'Campus Librarian');
        $staff = $this->staff($campus, 'Staff');
        [$project, $task] = $this->context($staff, 'completed', 100, '2026-08-02');
        $this->entry($staff, $project, $task, '2026-08-05', 60, ['output_deliverable' => 'Frozen achievement.']);

        $first = $this->actingAs($librarian)->post(route('campus-reports.finalize'), ['month' => 8, 'year' => 2026]);
        $report = CampusMonthlyReport::firstOrFail();
        $first->assertRedirect(route('campus-reports.show', $report));
        $code = $report->report_code;
        $this->entry($staff, $project, $task, '2026-08-06', 600, ['output_deliverable' => 'Late change.']);
        $this->actingAs($librarian)->post(route('campus-reports.finalize'), ['month' => 8, 'year' => 2026]);

        $this->assertDatabaseCount('campus_monthly_reports', 1);
        $this->assertSame($code, $report->fresh()->report_code);
        $view = $this->actingAs($librarian)->get(route('campus-reports.show', $report))->assertOk();
        $this->assertSame(60, $view->viewData('data')['performance']['total_minutes']);
        $view->assertSee('Frozen achievement.')->assertDontSee('Late change.');
    }

    public function test_another_campus_librarian_cannot_view_a_finalized_report(): void
    {
        $owner = $this->staff($this->campus('MAIN'), 'Campus Librarian');
        $other = $this->staff($this->campus('OTHER'), 'Campus Librarian');
        $this->actingAs($owner)->post(route('campus-reports.finalize'), ['month' => 8, 'year' => 2026]);

        $this->actingAs($other)->get(route('campus-reports.show', CampusMonthlyReport::firstOrFail()))->assertForbidden();
        $history = $this->actingAs($other)->get(route('campus-reports.index'))->assertOk()->viewData('history');
        $this->assertCount(0, $history);
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

    private function context(User $user, string $status, int $progress, string $assignedAt, ?string $due = null): array
    {
        $number = ++$this->sequence;
        $project = Project::create(['project_code' => 'PRJ-'.$number, 'title' => 'Project '.$number, 'project_category_id' => ProjectCategory::firstOrFail()->id, 'owner_id' => $user->id, 'created_by' => $user->id, 'start_date' => '2026-08-01', 'due_date' => '2026-09-01', 'scope' => 'selected_campuses', 'priority_level' => 'medium', 'progress_method' => 'tasks', 'progress_percentage' => $progress, 'status' => 'in_progress', 'is_active' => true]);
        $project->campuses()->attach($user->staffProfile->campus_id);
        $task = Task::create(['task_code' => 'TSK-'.$number, 'project_id' => $project->id, 'title' => 'Task '.$number, 'created_by' => $user->id, 'assigned_by' => $user->id, 'due_date' => $due ?? '2026-08-25', 'priority' => 'medium', 'status' => $status, 'progress_percentage' => $progress, 'is_active' => true]);
        $task->taskAssignees()->create(['user_id' => $user->id, 'assigned_at' => $assignedAt, 'is_active' => true]);

        return [$project, $task];
    }

    private function entry(User $user, Project $project, Task $task, string $date, int $minutes, array $extra = []): WorkEntry
    {
        return WorkEntry::create($extra + ['entry_code' => 'ENT-'.++$this->sequence, 'user_id' => $user->id, 'project_id' => $project->id, 'task_id' => $task->id, 'work_date' => $date, 'start_time' => '08:00', 'end_time' => '10:00', 'duration_minutes' => $minutes, 'work_description' => 'Report test work.']);
    }
}

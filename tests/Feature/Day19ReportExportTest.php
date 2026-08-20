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
use App\Services\TimesheetReportService;
use Database\Seeders\ProjectCategorySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Day19ReportExportTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, ProjectCategorySeeder::class]);
        $this->travelTo('2026-08-20 12:00:00');
    }

    public function test_staff_prints_and_exports_only_own_monthly_timesheet_with_period_isolation(): void
    {
        $campus = $this->campus('MAIN');
        $staff = $this->staff($campus, 'Staff', 'Nekesa Eve');
        $other = $this->staff($campus, 'Staff', 'Other Person');
        [$project, $task] = $this->context($staff);
        [$otherProject, $otherTask] = $this->context($other);
        $this->entry($staff, $project, $task, '2026-08-05', 'WEN-AUGUST', ['output_deliverable' => 'August output', 'work_location' => 'Main Library']);
        $this->entry($staff, $project, $task, '2026-07-05', 'WEN-JULY');
        $this->entry($other, $otherProject, $otherTask, '2026-08-06', 'WEN-PRIVATE');

        $print = $this->actingAs($staff)->get(route('my-work.timesheet.print', ['month' => 8, 'year' => 2026, 'user_id' => $other->id]));
        $print->assertOk()->assertSee('Monthly Staff Timesheet')->assertSee('WEN-AUGUST')
            ->assertSee('August output')->assertSee('Work Location')->assertSee('Main Library')->assertDontSee('WEN-JULY')->assertDontSee('WEN-PRIVATE')
            ->assertDontSee('sidebar', false);

        $csv = $this->actingAs($staff)->get(route('my-work.timesheet.csv', ['month' => 8, 'year' => 2026, 'user_id' => $other->id]));
        $csv->assertOk()->assertDownload('timesheet_Nekesa_Eve_2026_08.csv');
        $this->assertStringContainsString('text/csv', (string) $csv->headers->get('content-type'));
        ob_start();
        $csv->sendContent();
        $content = (string) ob_get_clean();
        $this->assertStringContainsString('Work-entry Code', $content);
        $this->assertStringContainsString('Work Location', $content);
        $this->assertStringContainsString('Main Library', $content);
        $this->assertStringContainsString('WEN-AUGUST', $content);
        $this->assertStringNotContainsString('WEN-PRIVATE', $content);

        $this->actingAs($staff)->get(route('my-work.timesheet.pdf', ['month' => 8, 'year' => 2026]))
            ->assertOk()->assertDownload('timesheet_Nekesa_Eve_2026_08.pdf');
        $pdfData = app(TimesheetReportService::class)->monthlyFor($staff, 8, 2026);
        $this->assertSame('Main Library', $pdfData['entries']->firstWhere('entry_code', 'WEN-AUGUST')->work_location);
    }

    public function test_invalid_period_is_rejected_for_print_and_exports(): void
    {
        $staff = $this->staff($this->campus('MAIN'), 'Staff');

        $this->actingAs($staff)->get(route('my-work.timesheet.print', ['month' => 13, 'year' => 2026]))->assertSessionHasErrors('month');
        $this->actingAs($staff)->get(route('my-work.monthly-report.pdf', ['month' => 8, 'year' => 1999]))->assertSessionHasErrors('year');
    }

    public function test_individual_print_and_pdf_reuse_snapshot_without_creating_or_changing_workflow(): void
    {
        $campus = $this->campus('MAIN');
        $staff = $this->staff($campus, 'Staff');
        $supervisor = $this->staff($campus, 'Campus Librarian');
        $staff->staffProfile->update(['supervisor_id' => $supervisor->id]);
        [$project, $task] = $this->context($staff);
        $this->entry($staff, $project, $task, '2026-08-05', 'WEN-FROZEN', ['output_deliverable' => 'Frozen achievement']);
        $this->actingAs($staff)->post(route('my-work.monthly-report.submit'), ['month' => 8, 'year' => 2026]);
        $report = MonthlyReport::firstOrFail();
        $original = $report->only(['status', 'submitted_snapshot', 'submitted_at', 'approved_at']);
        $this->entry($staff, $project, $task, '2026-08-06', 'WEN-LATE', ['output_deliverable' => 'Late live change']);

        $this->actingAs($staff)->get(route('my-work.monthly-report.print', ['month' => 8, 'year' => 2026]))
            ->assertOk()->assertSee($report->report_code)->assertSee('Frozen achievement')->assertDontSee('Late live change');
        $this->actingAs($staff)->get(route('my-work.monthly-report.pdf', ['month' => 8, 'year' => 2026]))
            ->assertOk()->assertDownload('monthly-report_'.$report->report_code.'.pdf');

        $this->assertDatabaseCount('monthly_reports', 1);
        $fresh = $report->fresh();
        $this->assertSame($original['status'], $fresh->status);
        $this->assertSame($original['submitted_snapshot'], $fresh->submitted_snapshot);
        $this->assertTrue($original['submitted_at']->equalTo($fresh->submitted_at));
        $this->assertNull($fresh->approved_at);
        $this->actingAs($supervisor)->get(route('my-work.monthly-report.print', ['month' => 8, 'year' => 2026]))
            ->assertOk()->assertDontSee($report->report_code);
    }

    public function test_campus_exports_enforce_scope_and_finalized_outputs_use_frozen_snapshot(): void
    {
        $main = $this->campus('MAIN');
        $other = $this->campus('OTHER');
        $owner = $this->staff($main, 'Campus Librarian');
        $outsider = $this->staff($other, 'Campus Librarian');
        $staff = $this->staff($main, 'Staff', 'Campus Staff');
        [$project, $task] = $this->context($staff);
        $this->entry($staff, $project, $task, '2026-08-05', 'WEN-FROZEN', ['output_deliverable' => 'Frozen campus achievement']);
        $this->actingAs($owner)->post(route('campus-reports.finalize'), ['month' => 8, 'year' => 2026]);
        $report = CampusMonthlyReport::firstOrFail();
        $snapshot = $report->snapshot;
        $this->entry($staff, $project, $task, '2026-08-06', 'WEN-LATE', ['output_deliverable' => 'Late campus mutation']);

        $this->actingAs($owner)->get(route('campus-reports.print', $report))->assertOk()
            ->assertSee('Frozen campus achievement')->assertDontSee('Late campus mutation');
        $this->actingAs($owner)->get(route('campus-reports.staff-csv', $report))->assertOk()
            ->assertDownload('campus-staff_MAIN_Campus_2026_08.csv');
        $this->actingAs($owner)->get(route('campus-reports.projects-csv', $report))->assertOk()
            ->assertDownload('campus-projects_MAIN_Campus_2026_08.csv');
        $this->actingAs($outsider)->get(route('campus-reports.print', $report))->assertForbidden();
        $this->actingAs($outsider)->get(route('campus-reports.staff-csv', $report))->assertForbidden();

        $this->assertSame($snapshot, $report->fresh()->snapshot);
        $this->assertDatabaseCount('campus_monthly_reports', 1);
    }

    public function test_university_librarian_can_export_finalized_snapshot_and_institution_csv_read_only(): void
    {
        $main = $this->campus('MAIN');
        $other = $this->campus('OTHER');
        $viewer = $this->staff($main, 'University Librarian');
        $finalizer = $this->staff($other, 'Campus Librarian');
        $report = CampusMonthlyReport::create([
            'report_code' => 'CMR-2026-0009', 'campus_id' => $other->id, 'reporting_month' => 8,
            'reporting_year' => 2026, 'status' => 'finalized', 'finalized_by' => $finalizer->id,
            'finalized_at' => now(), 'snapshot' => $this->snapshot('University frozen value'),
        ]);

        $this->actingAs($viewer)->get(route('campus-reports.print', $report))->assertOk()->assertSee('University frozen value');
        $this->actingAs($viewer)->get(route('campus-reports.pdf', $report))->assertOk()->assertDownload('campus-report_CMR-2026-0009.pdf');
        $this->actingAs($viewer)->get(route('university-dashboard.csv', ['month' => 8, 'year' => 2026]))
            ->assertOk()->assertDownload('university-performance_2026_08.csv');
        $this->assertSame('University frozen value', $report->fresh()->snapshot['narrative']['achievements'][0]['text']);
        $this->assertDatabaseCount('campus_monthly_reports', 1);
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

    private function context(User $user): array
    {
        $number = ++$this->sequence;
        $project = Project::create(['project_code' => 'PRJ-'.$number, 'title' => 'Project '.$number, 'project_category_id' => ProjectCategory::firstOrFail()->id, 'owner_id' => $user->id, 'created_by' => $user->id, 'start_date' => '2026-08-01', 'due_date' => '2026-09-01', 'scope' => 'selected_campuses', 'priority_level' => 'medium', 'progress_method' => 'tasks', 'progress_percentage' => 50, 'status' => 'in_progress', 'is_active' => true]);
        $project->campuses()->attach($user->staffProfile->campus_id);
        $task = Task::create(['task_code' => 'TSK-'.$number, 'project_id' => $project->id, 'title' => 'Task '.$number, 'created_by' => $user->id, 'assigned_by' => $user->id, 'due_date' => '2026-08-25', 'priority' => 'medium', 'status' => 'in_progress', 'progress_percentage' => 50, 'is_active' => true]);
        $task->taskAssignees()->create(['user_id' => $user->id, 'assigned_at' => '2026-08-01', 'is_active' => true]);

        return [$project, $task];
    }

    private function entry(User $user, Project $project, Task $task, string $date, string $code, array $extra = []): WorkEntry
    {
        return WorkEntry::create($extra + ['entry_code' => $code, 'user_id' => $user->id, 'project_id' => $project->id, 'task_id' => $task->id, 'work_date' => $date, 'start_time' => '08:00', 'end_time' => '10:00', 'duration_minutes' => 120, 'work_description' => 'Day 19 export test work.']);
    }

    private function snapshot(string $achievement): array
    {
        return ['identity' => ['campus' => 'OTHER Campus', 'period' => 'August 2026', 'month' => 8, 'year' => 2026], 'campus' => ['name' => 'OTHER Campus', 'campus_librarian' => 'Campus Librarian', 'libraries' => 0, 'total_staff' => 1, 'active_staff' => 1], 'performance' => ['total_hours' => '2 hours', 'staff_reporting' => 1, 'tasks_assigned' => 0, 'tasks_completed' => 0, 'tasks_in_progress' => 0, 'pending_review' => 0, 'overdue' => 0, 'completion_rate' => 0, 'active_projects' => 0, 'average_project_progress' => 0], 'staff_rows' => [], 'project_rows' => [], 'task_status' => [], 'report_status' => [], 'narrative' => ['achievements' => [['text' => $achievement, 'staff' => 'Staff']], 'challenges' => [], 'corrective_actions' => [], 'support_required' => [], 'planned_activities' => []]];
    }
}

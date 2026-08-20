<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Library;
use App\Models\MonthlyReport;
use App\Models\Position;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\StaffProfile;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkEntry;
use Database\Seeders\ProjectCategorySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndividualMonthlyReportTest extends TestCase
{
    use RefreshDatabase;

    private int $projectSequence = 0;

    private int $taskSequence = 0;

    private int $entrySequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, ProjectCategorySeeder::class]);
        $this->travelTo('2026-08-18 12:00:00');
    }

    public function test_authenticated_user_can_view_personal_report_and_guest_cannot(): void
    {
        $this->get(route('my-work.monthly-report'))->assertRedirect(route('login'));

        $user = $this->user();
        $this->actingAs($user)->get(route('my-work.monthly-report'))
            ->assertOk()
            ->assertSee('Individual Monthly Report')
            ->assertSee($user->name)
            ->assertSee('Draft');
    }

    public function test_current_period_defaults_and_valid_period_can_be_selected(): void
    {
        $user = $this->user();
        $default = $this->actingAs($user)->get(route('my-work.monthly-report'))->assertOk();
        $this->assertSame(['month' => 8, 'year' => 2026, 'label' => 'August 2026'], $default->viewData('period'));

        $selected = $this->actingAs($user)->get(route('my-work.monthly-report', ['month' => 2, 'year' => 2025]))->assertOk();
        $this->assertSame(['month' => 2, 'year' => 2025, 'label' => 'February 2025'], $selected->viewData('period'));
    }

    public function test_invalid_month_and_year_are_rejected_safely(): void
    {
        $user = $this->user();

        $this->actingAs($user)->get(route('my-work.monthly-report', ['month' => 13, 'year' => 2026]))
            ->assertSessionHasErrors('month');
        $this->actingAs($user)->get(route('my-work.monthly-report', ['month' => 8, 'year' => 1999]))
            ->assertSessionHasErrors('year');
        $this->actingAs($user)->get(route('my-work.monthly-report', ['month' => 'invalid', 'year' => 'invalid']))
            ->assertSessionHasErrors(['month', 'year']);
    }

    public function test_staff_information_uses_authenticated_users_profile_and_ignores_owner_tampering(): void
    {
        $user = $this->user();
        $other = $this->user();
        $supervisor = $this->user('Campus Librarian');
        $campus = Campus::create(['code' => 'MAIN', 'name' => 'Main Campus', 'is_active' => true]);
        $library = Library::create(['campus_id' => $campus->id, 'code' => 'MAIN-LIB', 'name' => 'Main Library', 'is_active' => true]);
        $position = Position::create(['code' => 'LIB-OFF', 'name' => 'Library Officer', 'is_active' => true]);
        StaffProfile::create(['user_id' => $user->id, 'staff_number' => 'MRP-STAFF-001', 'campus_id' => $campus->id, 'library_id' => $library->id, 'position_id' => $position->id, 'supervisor_id' => $supervisor->id, 'status' => 'active']);

        $response = $this->actingAs($user)->get(route('my-work.monthly-report', [
            'month' => 8,
            'year' => 2026,
            'user_id' => $other->id,
        ]))->assertOk();

        $this->assertSame([
            'name' => $user->name,
            'position' => 'Library Officer',
            'campus' => 'Main Campus',
            'library' => 'Main Library',
            'supervisor' => $supervisor->name,
        ], $response->viewData('staff'));
        $response->assertDontSee($other->name);
    }

    public function test_empty_month_loads_without_creating_a_report_record(): void
    {
        $user = $this->user();

        $response = $this->actingAs($user)->get(route('my-work.monthly-report', ['month' => 1, 'year' => 2020]))->assertOk();

        $this->assertNull($response->viewData('report'));
        $this->assertSame('draft', $response->viewData('status'));
        $this->assertSame([
            'total_hours' => '0 hours',
            'days_reported' => 0,
            'tasks_assigned' => 0,
            'tasks_completed' => 0,
            'pending_tasks' => 0,
            'overdue_tasks' => 0,
            'completion_rate' => 0.0,
            'project_performance' => 0.0,
        ], $response->viewData('performance'));
        $this->assertDatabaseCount('monthly_reports', 0);
    }

    public function test_selected_month_hours_and_distinct_days_are_personal_and_period_scoped(): void
    {
        $user = $this->user();
        $other = $this->user();
        [$project, $task] = $this->workContext($user, '2026-08-01');
        [$otherProject, $otherTask] = $this->workContext($other, '2026-08-01');
        $this->entry($user, $project, $task, '2026-08-03', 90);
        $this->entry($user, $project, $task, '2026-08-03', 30);
        $this->entry($user, $project, $task, '2026-09-03', 600);
        $this->entry($other, $otherProject, $otherTask, '2026-08-04', 900);

        $august = $this->actingAs($user)->get(route('my-work.monthly-report', ['month' => 8, 'year' => 2026]))->assertOk()->viewData('performance');
        $september = $this->actingAs($user)->get(route('my-work.monthly-report', ['month' => 9, 'year' => 2026]))->assertOk()->viewData('performance');

        $this->assertSame('2 hours', $august['total_hours']);
        $this->assertSame(1, $august['days_reported']);
        $this->assertSame('10 hours', $september['total_hours']);
        $this->assertSame(1, $september['days_reported']);
        $this->assertSame(0, $september['tasks_assigned']);
    }

    public function test_task_metrics_use_active_personal_assignments_and_existing_statuses(): void
    {
        $user = $this->user();
        $other = $this->user();
        $project = $this->project($user);
        $this->assignedTask($project, $user, '2026-08-02', ['status' => 'completed', 'progress_percentage' => 100]);
        $this->assignedTask($project, $user, '2026-08-03', ['status' => 'in_progress', 'progress_percentage' => 40, 'due_date' => '2026-08-17']);
        $this->assignedTask($project, $user, '2026-08-04', ['status' => 'pending_review', 'progress_percentage' => 100]);
        $this->assignedTask($project, $user, '2026-08-05', ['status' => 'deferred', 'progress_percentage' => 20]);
        $this->assignedTask($project, $user, '2026-08-06', ['status' => 'not_started', 'progress_percentage' => 0]);
        $this->assignedTask($project, $user, '2026-08-07', ['status' => 'cancelled']);
        $this->assignedTask($project, $user, '2026-08-08', ['status' => 'in_progress', 'is_active' => false]);
        $inactiveAssignment = $this->task($project, $user, ['status' => 'in_progress']);
        $inactiveAssignment->assignees()->attach($user, ['assigned_at' => '2026-08-09', 'is_active' => false]);
        $this->assignedTask($project, $other, '2026-08-10', ['status' => 'completed', 'progress_percentage' => 100]);
        $this->task($project, $user, ['status' => 'completed', 'progress_percentage' => 100]);

        $performance = $this->actingAs($user)->get(route('my-work.monthly-report', ['month' => 8, 'year' => 2026]))->assertOk()->viewData('performance');

        $this->assertSame(5, $performance['tasks_assigned']);
        $this->assertSame(1, $performance['tasks_completed']);
        $this->assertSame(4, $performance['pending_tasks']);
        $this->assertSame(1, $performance['overdue_tasks']);
        $this->assertSame(20.0, $performance['completion_rate']);
        $this->assertSame(52.0, $performance['project_performance']);
    }

    public function test_historical_overdue_uses_selected_month_end_without_manufacturing_status_snapshots(): void
    {
        $user = $this->user();
        $project = $this->project($user);
        $julyOverdue = $this->assignedTask($project, $user, '2026-07-02', ['status' => 'in_progress', 'due_date' => '2026-07-15']);
        $this->assignedTask($project, $user, '2026-07-03', ['status' => 'in_progress', 'due_date' => '2026-08-01']);
        $this->assignedTask($project, $user, '2026-07-04', ['status' => 'completed', 'due_date' => '2026-07-10']);

        $performance = $this->actingAs($user)->get(route('my-work.monthly-report', ['month' => 7, 'year' => 2026]))->assertOk()->viewData('performance');

        $this->assertTrue($julyOverdue->is_overdue);
        $this->assertSame(1, $performance['overdue_tasks']);
    }

    public function test_privileged_role_calculations_remain_personal(): void
    {
        foreach (['Campus Librarian', 'University Librarian', 'Administrator'] as $role) {
            $viewer = $this->user($role);
            $other = $this->user();
            [$ownProject, $ownTask] = $this->workContext($viewer, '2026-08-01');
            [$otherProject, $otherTask] = $this->workContext($other, '2026-08-01');
            $this->entry($viewer, $ownProject, $ownTask, '2026-08-10', 60);
            $this->entry($other, $otherProject, $otherTask, '2026-08-10', 600);

            $performance = $this->actingAs($viewer)->get(route('my-work.monthly-report', ['month' => 8, 'year' => 2026]))->assertOk()->viewData('performance');

            $this->assertSame('1 hour', $performance['total_hours'], $role);
            $this->assertSame(1, $performance['tasks_assigned'], $role);
        }
    }

    public function test_monthly_report_period_is_unique_per_user(): void
    {
        $user = $this->user();
        MonthlyReport::create(['report_code' => 'MRP-2026-0001', 'user_id' => $user->id, 'reporting_month' => 8, 'reporting_year' => 2026, 'status' => 'draft']);

        $this->expectException(UniqueConstraintViolationException::class);
        MonthlyReport::create(['report_code' => 'MRP-2026-0002', 'user_id' => $user->id, 'reporting_month' => 8, 'reporting_year' => 2026, 'status' => 'draft']);
    }

    public function test_monthly_narrative_uses_selected_month_work_entries_and_deduplicates_values(): void
    {
        $user = $this->user();
        [$project, $task] = $this->workContext($user, '2026-08-01');
        $this->entry($user, $project, $task, '2026-08-02', 60, [
            'output_deliverable' => 'Responsive navigation completed.',
            'challenge_encountered' => 'Mobile spacing was inconsistent.',
            'corrective_action' => 'Adjusted responsive breakpoints.',
            'support_required' => 'Approved media assets required.',
            'planned_next_activity' => 'Complete accessibility improvements.',
        ]);
        $this->entry($user, $project, $task, '2026-08-03', 60, ['output_deliverable' => 'Responsive navigation completed.']);
        $this->entry($user, $project, $task, '2026-07-31', 60, [
            'output_deliverable' => 'July-only deliverable.',
            'challenge_encountered' => 'July-only challenge.',
        ]);

        $response = $this->actingAs($user)->get(route('my-work.monthly-report', ['month' => 8, 'year' => 2026]))->assertOk();

        $this->assertSame([
            'key_achievements' => ['Responsive navigation completed.'],
            'challenges' => ['Mobile spacing was inconsistent.'],
            'corrective_actions' => ['Adjusted responsive breakpoints.'],
            'support_required' => ['Approved media assets required.'],
            'planned_activities_next_month' => ['Complete accessibility improvements.'],
        ], $response->viewData('narrative'));
        $response->assertDontSee('July-only deliverable.')->assertDontSee('July-only challenge.');
    }

    public function test_monthly_narrative_ignores_other_users_and_created_at_for_period_membership(): void
    {
        $user = $this->user('Administrator');
        $other = $this->user();
        [$project, $task] = $this->workContext($user, '2026-08-01');
        [$otherProject, $otherTask] = $this->workContext($other, '2026-08-01');
        $own = $this->entry($user, $project, $task, '2026-08-05', 60, ['output_deliverable' => 'Personal August output.']);
        $own->timestamps = false;
        $own->forceFill(['created_at' => '2026-07-01 08:00:00'])->save();
        $this->entry($other, $otherProject, $otherTask, '2026-08-05', 60, ['output_deliverable' => 'Another employee output.']);

        $response = $this->actingAs($user)->get(route('my-work.monthly-report', ['month' => 8, 'year' => 2026]))->assertOk();

        $response->assertSee('Personal August output.')->assertDontSee('Another employee output.');
        $this->assertSame(['Personal August output.'], $response->viewData('narrative')['key_achievements']);
    }

    public function test_empty_month_displays_automatic_narrative_empty_states(): void
    {
        $this->actingAs($this->user())->get(route('my-work.monthly-report', ['month' => 1, 'year' => 2020]))
            ->assertOk()
            ->assertSee('No achievements/deliverables recorded for this period.')
            ->assertSee('No challenges recorded for this period.')
            ->assertSee('No corrective actions recorded for this period.')
            ->assertSee('No support requirements recorded for this period.')
            ->assertSee('No planned follow-up activities recorded for this period.');
    }

    public function test_existing_monthly_report_is_preserved_but_its_manual_narrative_is_not_displayed(): void
    {
        $user = $this->user();
        $report = MonthlyReport::create(['report_code' => 'MRP-2026-0001', 'user_id' => $user->id, 'reporting_month' => 8, 'reporting_year' => 2026, 'status' => 'draft', 'key_achievements' => 'Legacy manual narrative.']);

        $this->actingAs($user)->get(route('my-work.monthly-report', ['month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertSee('MRP-2026-0001')
            ->assertDontSee('Legacy manual narrative.');

        $this->assertSame('Legacy manual narrative.', $report->fresh()->key_achievements);
        $this->assertDatabaseCount('monthly_reports', 1);
    }

    public function test_privileged_roles_still_receive_only_their_personal_report_identity(): void
    {
        foreach (['Campus Librarian', 'University Librarian', 'Administrator'] as $role) {
            $viewer = $this->user($role);
            $other = $this->user();

            $response = $this->actingAs($viewer)->get(route('my-work.monthly-report', ['user_id' => $other->id]))->assertOk();

            $this->assertSame($viewer->name, $response->viewData('staff')['name'], $role);
            $response->assertDontSee($other->name);
        }
    }

    public function test_opening_report_does_not_mutate_work_task_project_or_create_report(): void
    {
        $user = $this->user();
        $category = ProjectCategory::firstOrFail();
        $project = Project::create(['project_code' => 'PRJ-MRP-0001', 'title' => 'Monthly Report Project', 'project_category_id' => $category->id, 'owner_id' => $user->id, 'created_by' => $user->id, 'start_date' => '2026-01-01', 'due_date' => '2026-12-31', 'scope' => 'university_wide', 'priority_level' => 'medium', 'progress_method' => 'manual', 'progress_percentage' => 25, 'status' => 'in_progress', 'is_active' => true]);
        $task = Task::create(['task_code' => 'TSK-MRP-0001', 'project_id' => $project->id, 'title' => 'Monthly Report Task', 'created_by' => $user->id, 'assigned_by' => $user->id, 'status' => 'in_progress', 'priority' => 'medium', 'progress_percentage' => 40, 'is_active' => true]);
        $entry = WorkEntry::create(['entry_code' => 'WEN-MRP-0001', 'user_id' => $user->id, 'project_id' => $project->id, 'task_id' => $task->id, 'work_date' => '2026-08-10', 'start_time' => '09:00', 'end_time' => '10:00', 'duration_minutes' => 60, 'work_description' => 'Monthly report foundation test work.']);
        $beforeProject = $project->fresh()->only(['status', 'progress_percentage', 'updated_at']);
        $beforeTask = $task->fresh()->only(['status', 'progress_percentage', 'updated_at']);
        $beforeEntry = $entry->fresh()->only(['duration_minutes', 'work_date', 'updated_at']);

        $this->actingAs($user)->get(route('my-work.monthly-report'))->assertOk();

        $this->assertEquals($beforeProject, $project->fresh()->only(['status', 'progress_percentage', 'updated_at']));
        $this->assertEquals($beforeTask, $task->fresh()->only(['status', 'progress_percentage', 'updated_at']));
        $this->assertEquals($beforeEntry, $entry->fresh()->only(['duration_minutes', 'work_date', 'updated_at']));
        $this->assertDatabaseCount('work_entries', 1);
        $this->assertDatabaseCount('monthly_reports', 0);
    }

    private function user(string $role = 'Staff'): User
    {
        $user = User::factory()->create(['account_status' => 'active']);
        $user->assignRole($role);

        return $user;
    }

    private function workContext(User $user, string $assignedAt): array
    {
        $project = $this->project($user);

        return [$project, $this->assignedTask($project, $user, $assignedAt)];
    }

    private function project(User $owner): Project
    {
        $this->projectSequence++;

        return Project::create([
            'project_code' => 'PRJ-MRP-'.str_pad((string) $this->projectSequence, 4, '0', STR_PAD_LEFT),
            'title' => 'Monthly Report Project '.$this->projectSequence,
            'project_category_id' => ProjectCategory::firstOrFail()->id,
            'owner_id' => $owner->id,
            'created_by' => $owner->id,
            'start_date' => '2026-01-01',
            'due_date' => '2026-12-31',
            'scope' => 'university_wide',
            'priority_level' => 'medium',
            'progress_method' => 'manual',
            'progress_percentage' => 0,
            'status' => 'in_progress',
            'is_active' => true,
        ]);
    }

    private function task(Project $project, User $creator, array $overrides = []): Task
    {
        $this->taskSequence++;

        return Task::create(array_merge([
            'task_code' => 'TSK-MRP-'.str_pad((string) $this->taskSequence, 4, '0', STR_PAD_LEFT),
            'project_id' => $project->id,
            'title' => 'Monthly Report Task '.$this->taskSequence,
            'created_by' => $creator->id,
            'assigned_by' => $creator->id,
            'priority' => 'medium',
            'status' => 'not_started',
            'progress_percentage' => 0,
            'is_active' => true,
        ], $overrides));
    }

    private function assignedTask(Project $project, User $assignee, string $assignedAt, array $overrides = []): Task
    {
        $task = $this->task($project, $assignee, $overrides);
        $task->assignees()->attach($assignee, ['assigned_at' => $assignedAt, 'is_active' => true]);

        return $task;
    }

    private function entry(User $user, Project $project, Task $task, string $workDate, int $minutes, array $overrides = []): WorkEntry
    {
        $this->entrySequence++;

        return WorkEntry::create(array_merge([
            'entry_code' => 'WEN-MRP-'.str_pad((string) $this->entrySequence, 4, '0', STR_PAD_LEFT),
            'user_id' => $user->id,
            'project_id' => $project->id,
            'task_id' => $task->id,
            'work_date' => $workDate,
            'start_time' => '09:00',
            'end_time' => '10:00',
            'duration_minutes' => $minutes,
            'work_description' => 'Monthly report calculation test work.',
        ], $overrides));
    }
}

<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\StaffProfile;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\User;
use App\Models\WorkEntry;
use App\Models\WorkEntryActivity;
use Database\Seeders\ProjectCategorySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndividualDashboardTest extends TestCase
{
    use RefreshDatabase;

    private ProjectCategory $category;

    private int $projectSequence = 0;

    private int $taskSequence = 0;

    private int $entrySequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, ProjectCategorySeeder::class]);
        $this->category = ProjectCategory::firstOrFail();
        $this->travelTo('2026-08-18 12:00:00');
    }

    public function test_hours_and_reported_days_only_use_the_users_current_month_entries(): void
    {
        $user = $this->user();
        $other = $this->user();
        [$project, $task] = $this->context($user);
        [$otherProject, $otherTask] = $this->context($other);

        $this->entry($user, $project, $task, '2026-08-03', 90);
        $this->entry($user, $project, $task, '2026-08-03', 60);
        $this->entry($user, $project, $task, '2026-08-12', 180);
        $this->entry($user, $project, $task, '2026-07-31', 600);
        $this->entry($other, $otherProject, $otherTask, '2026-08-15', 900);

        $summary = $this->actingAs($user)->get(route('dashboard'))->assertOk()->viewData('summary');

        $this->assertSame('5.5 hours', $summary['hours_this_month']);
        $this->assertSame(2, $summary['days_reported']);
    }

    public function test_task_cards_only_count_active_assignments_for_the_authenticated_user(): void
    {
        $user = $this->user();
        $other = $this->user();
        $project = $this->project($user);
        $project->projectMembers()->create(['user_id' => $user->id, 'joined_at' => now(), 'is_active' => true]);

        $this->assignedTask($project, $user, ['status' => 'completed']);
        $this->assignedTask($project, $user, ['status' => 'in_progress']);
        $this->assignedTask($project, $user, ['status' => 'not_started']);
        $this->assignedTask($project, $user, ['status' => 'cancelled']);
        $this->assignedTask($project, $user, ['status' => 'in_progress', 'is_active' => false]);
        $this->assignedTask($project, $other, ['status' => 'completed']);
        $this->task($project, $user, ['status' => 'in_progress']);

        $summary = $this->actingAs($user)->get(route('dashboard'))->assertOk()->viewData('summary');

        $this->assertSame(3, $summary['assigned_tasks']);
        $this->assertSame(1, $summary['completed_tasks']);
        $this->assertSame(1, $summary['in_progress_tasks']);
        $this->assertSame(33.3, $summary['completion_rate']);
    }

    public function test_project_membership_does_not_count_unassigned_project_tasks(): void
    {
        $user = $this->user();
        $project = $this->project($user);
        $project->projectMembers()->create(['user_id' => $user->id, 'joined_at' => now(), 'is_active' => true]);
        $this->task($project, $user, ['status' => 'in_progress']);

        $summary = $this->actingAs($user)->get(route('dashboard'))->assertOk()->viewData('summary');

        $this->assertSame(0, $summary['assigned_tasks']);
        $this->assertSame(0.0, $summary['completion_rate']);
    }

    public function test_overdue_count_matches_the_existing_task_overdue_rule(): void
    {
        $user = $this->user();
        $project = $this->project($user);
        $overdue = $this->assignedTask($project, $user, ['status' => 'in_progress', 'due_date' => '2026-08-17']);
        $completed = $this->assignedTask($project, $user, ['status' => 'completed', 'due_date' => '2026-08-10']);
        $today = $this->assignedTask($project, $user, ['status' => 'not_started', 'due_date' => '2026-08-18']);
        $future = $this->assignedTask($project, $user, ['status' => 'in_progress', 'due_date' => '2026-08-19']);

        $this->assertTrue($overdue->is_overdue);
        $this->assertFalse($completed->is_overdue);
        $this->assertFalse($today->is_overdue);
        $this->assertFalse($future->is_overdue);
        $this->assertSame(1, $this->actingAs($user)->get(route('dashboard'))->viewData('summary')['overdue_tasks']);
    }

    public function test_active_projects_only_count_owned_or_active_member_projects(): void
    {
        $user = $this->user();
        $other = $this->user();
        $ownedProject = $this->project($user, ['status' => 'in_progress']);
        $memberProject = $this->project($other, ['status' => 'planned']);
        $memberProject->projectMembers()->create(['user_id' => $user->id, 'joined_at' => now(), 'is_active' => true]);
        $inactiveMembership = $this->project($other);
        $inactiveMembership->projectMembers()->create(['user_id' => $user->id, 'joined_at' => now(), 'left_at' => now(), 'is_active' => false]);
        $this->project($user, ['status' => 'cancelled']);
        $this->project($user, ['status' => 'completed']);
        $this->project($user, ['is_active' => false]);
        $this->project($other);

        $response = $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $listedIds = $response->viewData('activeProjects')->modelKeys();

        $this->assertSame(2, $response->viewData('summary')['active_projects']);
        $this->assertCount($response->viewData('summary')['active_projects'], $listedIds);
        $this->assertEqualsCanonicalizing([$ownedProject->id, $memberProject->id], $listedIds);
    }

    public function test_administrative_visibility_does_not_add_unrelated_active_projects(): void
    {
        foreach (['Administrator', 'Campus Librarian', 'University Librarian'] as $role) {
            $viewer = $this->user($role);
            $other = $this->user();
            $personal = $this->project($viewer, ['title' => $role.' Personal Project']);
            $unrelated = $this->project($other, ['title' => $role.' Unrelated Project']);

            $projects = $this->actingAs($viewer)->get(route('dashboard'))->assertOk()->viewData('activeProjects');

            $this->assertTrue($projects->contains($personal), $role);
            $this->assertFalse($projects->contains($unrelated), $role);
        }
    }

    public function test_upcoming_deadlines_are_personal_and_exclude_ineligible_tasks(): void
    {
        $user = $this->user();
        $other = $this->user();
        $project = $this->project($user);
        $project->projectMembers()->create(['user_id' => $user->id, 'joined_at' => now(), 'is_active' => true]);

        $dueToday = $this->assignedTask($project, $user, ['title' => 'Due Today', 'due_date' => '2026-08-18']);
        $dueTomorrow = $this->assignedTask($project, $user, ['title' => 'Due Tomorrow', 'due_date' => '2026-08-19']);
        $this->assignedTask($project, $other, ['title' => 'Other User Deadline', 'due_date' => '2026-08-20']);
        $this->task($project, $user, ['title' => 'Member Only Deadline', 'due_date' => '2026-08-20']);
        $this->assignedTask($project, $user, ['title' => 'Completed Deadline', 'status' => 'completed', 'due_date' => '2026-08-20']);
        $this->assignedTask($project, $user, ['title' => 'Cancelled Deadline', 'status' => 'cancelled', 'due_date' => '2026-08-20']);
        $this->assignedTask($project, $user, ['title' => 'Inactive Deadline', 'is_active' => false, 'due_date' => '2026-08-20']);
        $this->assignedTask($project, $user, ['title' => 'Past Deadline', 'due_date' => '2026-08-17']);
        $this->assignedTask($project, $user, ['title' => 'No Deadline', 'due_date' => null]);
        $this->assignedTask($project, $user, ['title' => 'Outside Window', 'due_date' => '2026-09-02']);

        $deadlines = $this->actingAs($user)->get(route('dashboard'))->assertOk()->viewData('upcomingDeadlines');

        $this->assertSame([$dueToday->id, $dueTomorrow->id], $deadlines->modelKeys());
    }

    public function test_upcoming_deadlines_are_sorted_and_limited_to_five(): void
    {
        $user = $this->user();
        $project = $this->project($user);
        $expected = [];

        foreach ([6, 1, 5, 2, 4, 3] as $days) {
            $task = $this->assignedTask($project, $user, [
                'title' => 'Deadline '.$days,
                'due_date' => today()->addDays($days)->toDateString(),
            ]);
            $expected[$days] = $task->id;
        }

        $deadlines = $this->actingAs($user)->get(route('dashboard'))->assertOk()->viewData('upcomingDeadlines');

        $this->assertSame([$expected[1], $expected[2], $expected[3], $expected[4], $expected[5]], $deadlines->modelKeys());
    }

    public function test_privileged_roles_do_not_receive_other_users_upcoming_deadlines(): void
    {
        foreach (['Administrator', 'Campus Librarian', 'University Librarian'] as $role) {
            $viewer = $this->user($role);
            $other = $this->user();
            if ($role === 'Campus Librarian') {
                StaffProfile::create(['user_id' => $other->id, 'staff_number' => 'DL-'.$other->id, 'supervisor_id' => $viewer->id, 'status' => 'active']);
            }
            $project = $this->project($other);
            $otherDeadline = $this->assignedTask($project, $other, ['due_date' => '2026-08-20']);

            $deadlines = $this->actingAs($viewer)->get(route('dashboard'))->assertOk()->viewData('upcomingDeadlines');

            $this->assertFalse($deadlines->contains($otherDeadline), $role);
        }
    }

    public function test_task_status_chart_uses_personal_active_assignments_and_shared_overdue_logic(): void
    {
        $user = $this->user();
        $other = $this->user();
        $project = $this->project($user);
        $this->assignedTask($project, $user, ['status' => 'completed']);
        $this->assignedTask($project, $user, ['status' => 'in_progress']);
        $this->assignedTask($project, $user, ['status' => 'in_progress', 'due_date' => '2026-08-17']);
        $this->assignedTask($project, $user, ['status' => 'cancelled']);
        $this->assignedTask($project, $user, ['status' => 'not_started', 'is_active' => false]);
        $this->assignedTask($project, $other, ['status' => 'completed']);
        $this->task($project, $user, ['status' => 'not_started']);

        $response = $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $chart = $response->viewData('chartData')['task_status'];
        $summary = $response->viewData('summary');
        $counts = array_combine($chart['labels'], $chart['values']);

        $this->assertSame(3, $chart['total']);
        $this->assertSame(1, $counts['Completed']);
        $this->assertSame(2, $counts['In Progress']);
        $this->assertSame(1, $chart['overdue']);
        $this->assertSame($summary['assigned_tasks'], array_sum($chart['values']));
        $this->assertSame($summary['completed_tasks'], $counts['Completed']);
        $this->assertSame($summary['in_progress_tasks'], $counts['In Progress']);
        $this->assertSame($summary['overdue_tasks'], $chart['overdue']);
    }

    public function test_hours_by_project_chart_groups_personal_current_month_stored_minutes(): void
    {
        $user = $this->user();
        $other = $this->user();
        [$firstProject, $firstTask] = $this->context($user);
        [$secondProject, $secondTask] = $this->context($user);
        [$otherProject, $otherTask] = $this->context($other);
        $this->entry($user, $firstProject, $firstTask, '2026-08-03', 90);
        $this->entry($user, $firstProject, $firstTask, '2026-08-04', 30);
        $this->entry($user, $secondProject, $secondTask, '2026-08-05', 75);
        $this->entry($user, $firstProject, $firstTask, '2026-07-31', 600);
        $this->entry($other, $otherProject, $otherTask, '2026-08-06', 900);

        $response = $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $chart = $response->viewData('chartData')['hours_by_project'];
        $hours = array_combine($chart['labels'], $chart['values']);

        $this->assertCount(2, $hours);
        $this->assertSame(2.0, $hours[$firstProject->project_code.' — '.$firstProject->title]);
        $this->assertSame(1.25, $hours[$secondProject->project_code.' — '.$secondProject->title]);
        $this->assertSame(195, $chart['total_minutes']);
        $this->assertSame('3.25 hours', $response->viewData('summary')['hours_this_month']);
        $this->assertEqualsWithDelta($chart['total_minutes'] / 60, array_sum($chart['values']), 0.01);
    }

    public function test_weekly_hours_chart_uses_work_date_and_always_contains_monday_to_sunday(): void
    {
        $user = $this->user();
        $other = $this->user();
        [$project, $task] = $this->context($user);
        [$otherProject, $otherTask] = $this->context($other);
        $this->entry($user, $project, $task, '2026-08-17', 60);
        $this->entry($user, $project, $task, '2026-08-19', 150);
        $this->entry($user, $project, $task, '2026-08-16', 600);
        $this->entry($other, $otherProject, $otherTask, '2026-08-18', 900);

        $chart = $this->actingAs($user)->get(route('dashboard'))->assertOk()->viewData('chartData')['weekly_hours'];

        $this->assertSame(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'], $chart['labels']);
        $this->assertSame(['2026-08-17', '2026-08-18', '2026-08-19', '2026-08-20', '2026-08-21', '2026-08-22', '2026-08-23'], $chart['dates']);
        $this->assertSame([1.0, 0.0, 2.5, 0.0, 0.0, 0.0, 0.0], $chart['values']);
    }

    public function test_privileged_and_supervisory_roles_still_receive_personal_totals_only(): void
    {
        foreach (['Administrator', 'Campus Librarian', 'University Librarian'] as $role) {
            $viewer = $this->user($role);
            $other = $this->user();
            if ($role === 'Campus Librarian') {
                StaffProfile::create(['user_id' => $other->id, 'staff_number' => 'SUP-'.$other->id, 'supervisor_id' => $viewer->id, 'status' => 'active']);
            }
            [$ownProject, $ownTask] = $this->context($viewer);
            [$otherProject, $otherTask] = $this->context($other);
            $ownTask->assignees()->attach($viewer, ['assigned_at' => now(), 'is_active' => true]);
            $otherTask->assignees()->attach($other, ['assigned_at' => now(), 'is_active' => true]);
            $this->entry($viewer, $ownProject, $ownTask, '2026-08-10', 60);
            $this->entry($other, $otherProject, $otherTask, '2026-08-10', 600);

            $response = $this->actingAs($viewer)->get(route('dashboard'))->assertOk();
            $summary = $response->viewData('summary');
            $charts = $response->viewData('chartData');
            $this->assertSame('1 hour', $summary['hours_this_month'], $role);
            $this->assertSame(1, $summary['assigned_tasks'], $role);
            $this->assertSame(60, $charts['hours_by_project']['total_minutes'], $role);
            $this->assertSame(1, $charts['task_status']['total'], $role);
        }
    }

    public function test_recent_activity_combines_real_personal_work_entry_evidence_and_task_history(): void
    {
        $user = $this->user();
        $other = $this->user();
        [$project, $task] = $this->context($user);
        [$otherProject, $otherTask] = $this->context($other);
        $task->assignees()->attach($user, ['assigned_at' => now(), 'is_active' => true]);
        $otherTask->assignees()->attach($other, ['assigned_at' => now(), 'is_active' => true]);
        $this->entry($user, $project, $task, '2026-08-18', 60);
        $ownEntry = WorkEntry::query()->latest('id')->firstOrFail();
        $this->entry($other, $otherProject, $otherTask, '2026-08-18', 60);
        $otherEntry = WorkEntry::query()->latest('id')->firstOrFail();
        $ownEntry->activities()->create(['user_id' => $user->id, 'event' => 'evidence_added', 'description' => 'EVD-2026-0001 — Work Sample', 'metadata' => ['evidence_code' => 'EVD-2026-0001']]);
        $ownEntry->activities()->create(['user_id' => $user->id, 'event' => 'evidence_removed', 'description' => 'EVD-2026-0002 — Old Sample', 'metadata' => ['evidence_code' => 'EVD-2026-0002']]);
        $task->activities()->create(['user_id' => $other->id, 'activity_type' => 'task_returned', 'message' => 'Please revise the deliverable.']);
        $otherEntry->activities()->create(['user_id' => $other->id, 'event' => 'evidence_added', 'description' => 'UNRELATED EVIDENCE']);
        $otherTask->activities()->create(['user_id' => $other->id, 'activity_type' => 'task_started', 'message' => 'UNRELATED TASK']);

        $activity = $this->actingAs($user)->get(route('dashboard'))->assertOk()->viewData('recentActivity');
        $titles = collect($activity)->pluck('title');
        $descriptions = collect($activity)->pluck('description');

        $this->assertTrue($titles->contains('Daily Work Entry Created'));
        $this->assertTrue($titles->contains('Evidence Added'));
        $this->assertTrue($titles->contains('Evidence Removed'));
        $this->assertTrue($titles->contains('Task Returned for Correction'));
        $this->assertFalse($descriptions->contains('UNRELATED EVIDENCE'));
        $this->assertFalse($descriptions->contains('UNRELATED TASK'));
    }

    public function test_recent_activity_is_newest_first_limited_and_does_not_invent_history(): void
    {
        $user = $this->user();
        $project = $this->project($user);
        $task = $this->assignedTask($project, $user);

        foreach (range(1, 9) as $number) {
            $activity = $task->activities()->create(['user_id' => $user->id, 'activity_type' => 'progress_updated', 'message' => 'Activity '.$number]);
            $activity->timestamps = false;
            $activity->forceFill(['created_at' => now()->addMinutes($number), 'updated_at' => now()->addMinutes($number)])->save();
        }

        $before = TaskActivity::count();
        $activity = $this->actingAs($user)->get(route('dashboard'))->assertOk()->viewData('recentActivity');

        $this->assertCount(8, $activity);
        $this->assertSame('Activity 9', $activity[0]['description']);
        $this->assertSame('Activity 2', $activity[7]['description']);
        $this->assertSame($before, TaskActivity::count());
    }

    public function test_personal_alerts_apply_deadline_review_and_deduplication_rules(): void
    {
        $user = $this->user();
        $other = $this->user();
        $project = $this->project($user);
        $overdue = $this->assignedTask($project, $user, ['status' => 'in_progress', 'due_date' => '2026-08-15']);
        $dueSoon = $this->assignedTask($project, $user, ['status' => 'not_started', 'due_date' => '2026-08-20']);
        $returned = $this->assignedTask($project, $user, ['status' => 'in_progress', 'returned_at' => now()]);
        $pending = $this->assignedTask($project, $user, ['status' => 'pending_review']);
        $this->assignedTask($project, $user, ['status' => 'completed', 'due_date' => '2026-08-10']);
        $this->assignedTask($project, $user, ['status' => 'cancelled', 'due_date' => '2026-08-10']);
        $this->assignedTask($project, $user, ['status' => 'in_progress', 'is_active' => false, 'due_date' => '2026-08-10']);
        $this->assignedTask($project, $user, ['status' => 'not_started', 'due_date' => '2026-08-22']);
        $this->assignedTask($project, $other, ['status' => 'in_progress', 'due_date' => '2026-08-10']);

        $alerts = collect($this->actingAs($user)->get(route('dashboard'))->assertOk()->viewData('alerts'));

        $this->assertSame(['due_soon', 'overdue', 'pending_review', 'returned_for_correction'], $alerts->pluck('type')->sort()->values()->all());
        $this->assertSame(1, $alerts->where('code', $overdue->task_code)->count());
        $this->assertSame('overdue', $alerts->firstWhere('code', $overdue->task_code)['type']);
        $this->assertSame('due_soon', $alerts->firstWhere('code', $dueSoon->task_code)['type']);
        $this->assertSame('returned_for_correction', $alerts->firstWhere('code', $returned->task_code)['type']);
        $this->assertSame('pending_review', $alerts->firstWhere('code', $pending->task_code)['type']);
    }

    public function test_privileged_roles_do_not_receive_global_or_supervisee_activity_and_alerts(): void
    {
        foreach (['Administrator', 'Campus Librarian', 'University Librarian'] as $role) {
            $viewer = $this->user($role);
            $other = $this->user();
            if ($role === 'Campus Librarian') {
                StaffProfile::create(['user_id' => $other->id, 'staff_number' => 'ACT-'.$other->id, 'supervisor_id' => $viewer->id, 'status' => 'active']);
            }
            $project = $this->project($other);
            $task = $this->assignedTask($project, $other, ['status' => 'in_progress', 'due_date' => '2026-08-10']);
            $task->activities()->create(['user_id' => $other->id, 'activity_type' => 'task_started', 'message' => 'PRIVATE '.$role]);

            $response = $this->actingAs($viewer)->get(route('dashboard'))->assertOk();

            $this->assertFalse(collect($response->viewData('recentActivity'))->pluck('description')->contains('PRIVATE '.$role), $role);
            $this->assertFalse(collect($response->viewData('alerts'))->pluck('code')->contains($task->task_code), $role);
        }
    }

    public function test_privileged_users_receive_authorized_links_only_for_personal_feed_activity(): void
    {
        $administrator = $this->user('Administrator');
        $other = $this->user();
        $personalProject = $this->project($administrator);
        $personalTask = $this->assignedTask($personalProject, $administrator);
        $personalTask->activities()->create(['user_id' => $administrator->id, 'activity_type' => 'task_started', 'message' => 'Personal administrator activity']);
        $unrelatedProject = $this->project($other);
        $unrelatedTask = $this->assignedTask($unrelatedProject, $other);
        $unrelatedTask->activities()->create(['user_id' => $other->id, 'activity_type' => 'task_started', 'message' => 'Unrelated administrator-visible activity']);

        $activity = collect($this->actingAs($administrator)->get(route('dashboard'))->assertOk()->viewData('recentActivity'));

        $this->assertSame(route('tasks.show', $personalTask), $activity->firstWhere('description', 'Personal administrator activity')['url']);
        $this->assertNull($activity->firstWhere('description', 'Unrelated administrator-visible activity'));
    }

    public function test_inactive_assignment_is_excluded_from_all_personal_task_dashboard_data(): void
    {
        $user = $this->user();
        $project = $this->project($user);
        $task = $this->task($project, $user, ['status' => 'in_progress', 'due_date' => '2026-08-17']);
        $task->assignees()->attach($user, ['assigned_at' => now(), 'is_active' => false]);
        $task->activities()->create(['user_id' => $user->id, 'activity_type' => 'task_started', 'message' => 'Actor-owned activity remains legitimate']);

        $response = $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $this->assertSame(0, $response->viewData('summary')['assigned_tasks']);
        $this->assertSame(0, $response->viewData('chartData')['task_status']['total']);
        $this->assertCount(0, $response->viewData('upcomingDeadlines'));
        $this->assertCount(0, $response->viewData('alerts'));
    }

    public function test_dashboard_request_does_not_mutate_task_or_project_state(): void
    {
        $user = $this->user();
        $project = $this->project($user, ['status' => 'in_progress', 'progress_percentage' => 37]);
        $task = $this->assignedTask($project, $user, ['status' => 'in_progress', 'progress_percentage' => 42, 'due_date' => '2026-08-17']);
        $this->entry($user, $project, $task, '2026-08-18', 60);
        $entry = WorkEntry::query()->latest('id')->firstOrFail();
        $beforeProject = $project->fresh()->only(['status', 'progress_percentage', 'updated_at']);
        $beforeTask = $task->fresh()->only(['status', 'progress_percentage', 'updated_at']);
        $beforeEntry = $entry->only(['duration_minutes', 'work_date', 'updated_at']);
        $beforeWorkActivityCount = WorkEntryActivity::count();
        $beforeTaskActivityCount = TaskActivity::count();

        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $this->assertEquals($beforeProject, $project->fresh()->only(['status', 'progress_percentage', 'updated_at']));
        $this->assertEquals($beforeTask, $task->fresh()->only(['status', 'progress_percentage', 'updated_at']));
        $this->assertEquals($beforeEntry, $entry->fresh()->only(['duration_minutes', 'work_date', 'updated_at']));
        $this->assertSame($beforeWorkActivityCount, WorkEntryActivity::count());
        $this->assertSame($beforeTaskActivityCount, TaskActivity::count());
    }

    private function user(string $role = 'Staff'): User
    {
        $user = User::factory()->create(['account_status' => 'active']);
        $user->assignRole($role);

        return $user;
    }

    private function context(User $user): array
    {
        $project = $this->project($user);

        return [$project, $this->task($project, $user)];
    }

    private function project(User $owner, array $overrides = []): Project
    {
        $this->projectSequence++;

        return Project::create(array_merge([
            'project_code' => 'PRJ-DASH-'.str_pad((string) $this->projectSequence, 4, '0', STR_PAD_LEFT),
            'title' => 'Dashboard Project '.$this->projectSequence,
            'project_category_id' => $this->category->id,
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
        ], $overrides));
    }

    private function task(Project $project, User $creator, array $overrides = []): Task
    {
        $this->taskSequence++;

        return Task::create(array_merge([
            'task_code' => 'TSK-DASH-'.str_pad((string) $this->taskSequence, 4, '0', STR_PAD_LEFT),
            'project_id' => $project->id,
            'title' => 'Dashboard Task '.$this->taskSequence,
            'created_by' => $creator->id,
            'assigned_by' => $creator->id,
            'priority' => 'medium',
            'status' => 'not_started',
            'progress_percentage' => 0,
            'is_active' => true,
        ], $overrides));
    }

    private function assignedTask(Project $project, User $assignee, array $overrides = []): Task
    {
        $task = $this->task($project, $assignee, $overrides);
        $task->assignees()->attach($assignee, ['assigned_at' => now(), 'is_active' => true]);

        return $task;
    }

    private function entry(User $user, Project $project, Task $task, string $date, int $minutes): void
    {
        $this->entrySequence++;
        WorkEntry::create([
            'entry_code' => 'WEN-DASH-'.str_pad((string) $this->entrySequence, 4, '0', STR_PAD_LEFT),
            'user_id' => $user->id,
            'project_id' => $project->id,
            'task_id' => $task->id,
            'work_date' => $date,
            'start_time' => '09:00',
            'end_time' => '10:00',
            'duration_minutes' => $minutes,
            'work_description' => 'Dashboard summary test work.',
        ]);
    }
}

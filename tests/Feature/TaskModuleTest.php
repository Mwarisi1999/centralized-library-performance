<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\StaffProfile;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\ProjectCategorySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TaskModuleTest extends TestCase
{
    use RefreshDatabase;

    private Campus $campus;

    private ProjectCategory $category;

    private int $projectSequence = 0;

    private int $taskSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed([RolePermissionSeeder::class, ProjectCategorySeeder::class]);
        $this->campus = Campus::create(['name' => 'Main Campus', 'code' => 'MAIN', 'is_active' => true]);
        $this->category = ProjectCategory::firstOrFail();
    }

    public function test_authorized_roles_can_create_tasks_in_accessible_projects(): void
    {
        foreach (['Administrator', 'University Librarian', 'Campus Librarian', 'Staff'] as $role) {
            $creator = $this->user($role, in_array($role, ['Campus Librarian', 'Staff'], true) ? $this->campus : null);
            $project = $this->project($creator);
            $project->projectMembers()->create(['user_id' => $creator->id, 'joined_at' => now(), 'is_active' => true]);

            $this->actingAs($creator)->post(route('tasks.store'), $this->payload($project, $creator, [
                'title' => $role.' Task',
            ]))->assertRedirect();
            $this->assertDatabaseHas('tasks', ['title' => $role.' Task', 'created_by' => $creator->id, 'assigned_by' => $creator->id]);
        }
    }

    public function test_intern_and_monitoring_officer_cannot_create_tasks(): void
    {
        $owner = $this->user('Administrator');
        $project = $this->project($owner);

        foreach (['Intern', 'M&E Officer'] as $role) {
            $user = $this->user($role, $this->campus);
            $project->projectMembers()->create(['user_id' => $user->id, 'joined_at' => now(), 'is_active' => true]);
            $this->actingAs($user)->get(route('tasks.create'))->assertForbidden();
            $this->actingAs($user)->post(route('tasks.store'), $this->payload($project, $user))->assertForbidden();
        }
    }

    public function test_task_code_creator_assigned_by_and_multiple_assignees_are_stored(): void
    {
        $creator = $this->user('Administrator');
        $first = $this->user('Staff', $this->campus);
        $second = $this->user('Intern', $this->campus);
        $project = $this->project($creator);
        foreach ([$first, $second] as $member) {
            $project->projectMembers()->create(['user_id' => $member->id, 'joined_at' => now(), 'is_active' => true]);
        }

        $this->actingAs($creator)->post(route('tasks.store'), $this->payload($project, $first, [
            'assignee_ids' => [$first->id, $second->id],
        ]))->assertRedirect();

        $task = Task::firstOrFail();
        $this->assertSame('TSK-'.now()->year.'-0001', $task->task_code);
        $this->assertSame($creator->id, $task->created_by);
        $this->assertSame($creator->id, $task->assigned_by);
        $this->assertEqualsCanonicalizing([$first->id, $second->id], $task->assignees()->pluck('users.id')->all());
    }

    public function test_nonmember_and_inactive_or_suspended_members_cannot_be_assigned(): void
    {
        $creator = $this->user('Administrator');
        $project = $this->project($creator);
        $nonmember = $this->user('Staff');
        $inactive = User::factory()->create(['account_status' => 'inactive']);
        $suspended = User::factory()->create(['account_status' => 'suspended']);
        foreach ([$inactive, $suspended] as $member) {
            $project->projectMembers()->create(['user_id' => $member->id, 'joined_at' => now(), 'is_active' => true]);
        }

        $this->actingAs($creator)->post(route('tasks.store'), $this->payload($project, $nonmember, [
            'assignee_ids' => [$nonmember->id, $inactive->id, $suspended->id],
        ]))->assertSessionHasErrors(['assignee_ids.0', 'assignee_ids.1', 'assignee_ids.2']);
    }

    public function test_removed_project_member_cannot_be_assigned(): void
    {
        $creator = $this->user('Administrator');
        $removed = $this->user('Staff');
        $project = $this->project($creator);
        $project->projectMembers()->create([
            'user_id' => $removed->id, 'joined_at' => now()->subDay(), 'left_at' => now(), 'is_active' => false,
        ]);

        $this->actingAs($creator)->post(route('tasks.store'), $this->payload($project, $removed))
            ->assertSessionHasErrors('assignee_ids.0');
    }

    public function test_dates_and_progress_are_validated(): void
    {
        $creator = $this->user('Administrator');
        $member = $this->user('Staff');
        $project = $this->project($creator);
        $project->projectMembers()->create(['user_id' => $member->id, 'joined_at' => now(), 'is_active' => true]);

        $this->actingAs($creator)->post(route('tasks.store'), $this->payload($project, $member, [
            'start_date' => '2026-12-10', 'due_date' => '2026-12-09', 'progress_percentage' => -1,
        ]))->assertSessionHasErrors(['due_date', 'progress_percentage']);
        $this->actingAs($creator)->post(route('tasks.store'), $this->payload($project, $member, [
            'progress_percentage' => 101,
        ]))->assertSessionHasErrors('progress_percentage');
    }

    public function test_user_cannot_create_task_under_inaccessible_project(): void
    {
        $owner = $this->user('Administrator');
        $staff = $this->user('Staff');
        $assignee = $this->user('Intern');
        $project = $this->project($owner);
        $project->projectMembers()->create(['user_id' => $assignee->id, 'joined_at' => now(), 'is_active' => true]);

        $this->actingAs($staff)->post(route('tasks.store'), $this->payload($project, $assignee))
            ->assertSessionHasErrors('project_id');
    }

    public function test_staff_sees_assigned_created_and_member_project_tasks_but_not_unrelated_tasks(): void
    {
        $administrator = $this->user('Administrator');
        $staff = $this->user('Staff', $this->campus);
        $memberProject = $this->project($administrator);
        $memberProject->projectMembers()->create(['user_id' => $staff->id, 'joined_at' => now(), 'is_active' => true]);
        $this->task($memberProject, $administrator, ['title' => 'Member Project Task']);
        $assigned = $this->task($this->project($administrator), $administrator, ['title' => 'Assigned Task']);
        $assigned->assignees()->attach($staff, ['assigned_at' => now(), 'is_active' => true]);
        $this->task($this->project($administrator), $administrator, ['title' => 'Hidden Task']);

        $this->actingAs($staff)->get(route('tasks.index'))->assertOk()
            ->assertSee('Member Project Task')->assertSee('Assigned Task')->assertDontSee('Hidden Task');
    }

    public function test_intern_only_sees_assigned_tasks(): void
    {
        $administrator = $this->user('Administrator');
        $intern = $this->user('Intern', $this->campus);
        $project = $this->project($administrator);
        $project->projectMembers()->create(['user_id' => $intern->id, 'joined_at' => now(), 'is_active' => true]);
        $assigned = $this->task($project, $administrator, ['title' => 'Intern Assigned Task']);
        $assigned->assignees()->attach($intern, ['assigned_at' => now(), 'is_active' => true]);
        $this->task($project, $administrator, ['title' => 'Unassigned Project Task']);

        $this->actingAs($intern)->get(route('tasks.index'))->assertOk()
            ->assertSee('Intern Assigned Task')->assertDontSee('Unassigned Project Task');
    }

    public function test_university_librarian_and_monitoring_officer_see_all_tasks(): void
    {
        $administrator = $this->user('Administrator');
        $this->task($this->project($administrator), $administrator, ['title' => 'First Global Task']);
        $this->task($this->project($administrator), $administrator, ['title' => 'Second Global Task']);

        foreach (['University Librarian', 'M&E Officer'] as $role) {
            $viewer = $this->user($role);
            $this->actingAs($viewer)->get(route('tasks.index'))->assertOk()
                ->assertSee('First Global Task')->assertSee('Second Global Task');
        }
    }

    public function test_task_detail_and_project_task_section_load(): void
    {
        $administrator = $this->user('Administrator');
        $project = $this->project($administrator);
        $task = $this->task($project, $administrator, ['title' => 'Visible Detail Task']);
        $task->assignees()->attach($administrator, ['assigned_at' => now(), 'is_active' => true]);

        $this->actingAs($administrator)->get(route('tasks.show', $task))
            ->assertOk()->assertSee('Task Detail')->assertSee('Visible Detail Task');
        $this->actingAs($administrator)->get(route('projects.show', $project))
            ->assertOk()->assertSee('Project Tasks')->assertSee('Visible Detail Task');
    }

    private function user(string $role, ?Campus $campus = null): User
    {
        $user = User::factory()->create(['account_status' => 'active']);
        $user->assignRole($role);
        if ($campus) {
            StaffProfile::create(['user_id' => $user->id, 'staff_number' => 'TASK-'.str_pad((string) $user->id, 6, '0', STR_PAD_LEFT), 'campus_id' => $campus->id, 'status' => 'active']);
        }

        return $user;
    }

    private function project(User $owner): Project
    {
        $this->projectSequence++;

        return Project::create(['project_code' => 'PRJ-TASK-'.str_pad((string) $this->projectSequence, 4, '0', STR_PAD_LEFT), 'title' => 'Project '.$this->projectSequence, 'project_category_id' => $this->category->id, 'owner_id' => $owner->id, 'created_by' => $owner->id, 'start_date' => '2026-09-01', 'due_date' => '2027-01-31', 'scope' => 'university_wide', 'priority_level' => 'medium', 'progress_method' => 'manual', 'progress_percentage' => 0, 'status' => 'planned', 'is_active' => true]);
    }

    private function task(Project $project, User $creator, array $overrides = []): Task
    {
        $this->taskSequence++;

        return Task::create(array_merge(['task_code' => 'TSK-TEST-'.str_pad((string) $this->taskSequence, 4, '0', STR_PAD_LEFT), 'project_id' => $project->id, 'title' => 'Task '.$this->taskSequence, 'created_by' => $creator->id, 'assigned_by' => $creator->id, 'priority' => 'medium', 'status' => 'not_started', 'progress_percentage' => 0, 'is_active' => true], $overrides));
    }

    private function payload(Project $project, User $assignee, array $overrides = []): array
    {
        return array_merge(['project_id' => $project->id, 'title' => 'New Task', 'description' => 'Task description', 'assignee_ids' => [$assignee->id], 'start_date' => '2026-09-01', 'due_date' => '2026-12-01', 'priority' => 'medium', 'status' => 'not_started', 'progress_percentage' => 0, 'estimated_hours' => 8, 'remarks' => 'Task remarks'], $overrides);
    }
}

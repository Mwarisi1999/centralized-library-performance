<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\ProjectCategorySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProjectModuleTest extends TestCase
{
    use RefreshDatabase;

    private Campus $campusOne;

    private Campus $campusTwo;

    private ProjectCategory $category;

    private int $projectSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed([RolePermissionSeeder::class, ProjectCategorySeeder::class]);

        $this->campusOne = Campus::create(['name' => 'Main Campus', 'code' => 'MAIN', 'is_active' => true]);
        $this->campusTwo = Campus::create(['name' => 'Eastern Campus', 'code' => 'EAST', 'is_active' => true]);
        $this->category = ProjectCategory::firstOrFail();
    }

    public function test_authorized_roles_can_create_projects(): void
    {
        foreach (['Administrator', 'University Librarian', 'Campus Librarian'] as $role) {
            $creator = $this->user($role, $role === 'Campus Librarian' ? $this->campusOne : null);

            $this->actingAs($creator)->post(route('projects.store'), $this->validPayload($creator))
                ->assertRedirect();

            $this->assertDatabaseHas('projects', ['title' => "{$role} Project", 'created_by' => $creator->id]);
        }
    }

    public function test_staff_intern_and_monitoring_officer_cannot_create_projects(): void
    {
        foreach (['Staff', 'Intern', 'M&E Officer'] as $role) {
            $user = $this->user($role, $this->campusOne);

            $this->actingAs($user)->get(route('projects.create'))->assertForbidden();
            $this->actingAs($user)->post(route('projects.store'), $this->validPayload($user))->assertForbidden();
        }
    }

    public function test_staff_only_sees_projects_where_they_are_active_members(): void
    {
        $administrator = $this->user('Administrator');
        $staff = $this->user('Staff', $this->campusOne);
        $memberProject = $this->project($administrator, ['title' => 'Member Project']);
        $hiddenProject = $this->project($administrator, ['title' => 'Hidden Project']);
        $memberProject->members()->attach($staff, ['joined_at' => now(), 'is_active' => true]);

        $this->actingAs($staff)->get(route('projects.index'))
            ->assertOk()->assertSee('Member Project')->assertDontSee('Hidden Project');
        $this->actingAs($staff)->get(route('projects.show', $hiddenProject))->assertForbidden();
    }

    public function test_campus_librarian_sees_campus_member_owned_and_university_wide_projects(): void
    {
        $administrator = $this->user('Administrator');
        $librarian = $this->user('Campus Librarian', $this->campusOne);
        $campusProject = $this->project($administrator, ['title' => 'Campus Project', 'scope' => 'selected_campuses']);
        $campusProject->campuses()->attach($this->campusOne);
        $ownedProject = $this->project($librarian, ['title' => 'Owned Project']);
        $memberProject = $this->project($administrator, ['title' => 'Member Project']);
        $memberProject->members()->attach($librarian, ['joined_at' => now(), 'is_active' => true]);
        $universityProject = $this->project($administrator, ['title' => 'University Project']);
        $hiddenProject = $this->project($administrator, ['title' => 'Other Campus', 'scope' => 'selected_campuses']);
        $hiddenProject->campuses()->attach($this->campusTwo);

        $this->actingAs($librarian)->get(route('projects.index'))
            ->assertOk()
            ->assertSee('Campus Project')->assertSee('Owned Project')->assertSee('Member Project')
            ->assertSee('University Project')->assertDontSee('Other Campus');
    }

    public function test_university_librarian_and_monitoring_officer_see_all_projects(): void
    {
        $administrator = $this->user('Administrator');
        $this->project($administrator, ['title' => 'First Global Project']);
        $this->project($administrator, ['title' => 'Second Global Project']);

        foreach (['University Librarian', 'M&E Officer'] as $role) {
            $user = $this->user($role);
            $this->actingAs($user)->get(route('projects.index'))
                ->assertOk()->assertSee('First Global Project')->assertSee('Second Global Project');
        }
    }

    public function test_invalid_due_date_is_rejected(): void
    {
        $administrator = $this->user('Administrator');

        $this->actingAs($administrator)->post(route('projects.store'), $this->validPayload($administrator, [
            'start_date' => '2026-09-10', 'due_date' => '2026-09-09',
        ]))->assertSessionHasErrors('due_date');
    }

    public function test_selected_campus_scope_requires_at_least_one_campus(): void
    {
        $administrator = $this->user('Administrator');

        $this->actingAs($administrator)->post(route('projects.store'), $this->validPayload($administrator, [
            'scope' => 'selected_campuses', 'campus_ids' => [],
        ]))->assertSessionHasErrors('campus_ids');
    }

    public function test_multi_campus_project_and_owner_membership_are_created_transactionally(): void
    {
        $administrator = $this->user('Administrator');

        $this->actingAs($administrator)->post(route('projects.store'), $this->validPayload($administrator, [
            'scope' => 'selected_campuses',
            'campus_ids' => [$this->campusOne->id, $this->campusTwo->id],
            'member_ids' => [],
        ]))->assertRedirect();

        $project = Project::latest('id')->firstOrFail();
        $this->assertCount(2, $project->campuses);
        $this->assertDatabaseHas('project_members', [
            'project_id' => $project->id, 'user_id' => $administrator->id, 'is_active' => true,
        ]);
    }

    public function test_inactive_or_suspended_users_cannot_be_selected_as_owner_or_member(): void
    {
        $administrator = $this->user('Administrator');
        $inactive = User::factory()->create(['account_status' => 'inactive']);
        $suspended = User::factory()->create(['account_status' => 'suspended']);

        $this->actingAs($administrator)->post(route('projects.store'), $this->validPayload($administrator, [
            'owner_id' => $inactive->id,
            'member_ids' => [$suspended->id],
        ]))->assertSessionHasErrors(['owner_id', 'member_ids.0']);
    }

    public function test_project_code_uses_current_year_and_increments(): void
    {
        $administrator = $this->user('Administrator');

        $this->actingAs($administrator)->post(route('projects.store'), $this->validPayload($administrator, ['title' => 'First']))->assertRedirect();
        $this->actingAs($administrator)->post(route('projects.store'), $this->validPayload($administrator, ['title' => 'Second']))->assertRedirect();

        $year = now()->year;
        $this->assertDatabaseHas('projects', ['project_code' => "PRJ-{$year}-0001"]);
        $this->assertDatabaseHas('projects', ['project_code' => "PRJ-{$year}-0002"]);
    }

    public function test_search_and_combinable_filters_return_matching_project(): void
    {
        $administrator = $this->user('Administrator');
        $matching = $this->project($administrator, [
            'title' => 'Repository Modernisation', 'status' => 'in_progress', 'priority_level' => 'high',
            'scope' => 'selected_campuses',
        ]);
        $matching->campuses()->attach($this->campusOne);
        $this->project($administrator, ['title' => 'Unrelated Project', 'status' => 'planned', 'priority_level' => 'low']);

        $this->actingAs($administrator)->get(route('projects.index', [
            'search' => 'Modernisation', 'status' => 'in_progress', 'priority' => 'high',
            'category_id' => $this->category->id, 'campus_id' => $this->campusOne->id,
            'owner_id' => $administrator->id,
        ]))->assertOk()->assertSee('Repository Modernisation')->assertDontSee('Unrelated Project');
    }

    public function test_project_detail_loads_for_authorized_viewer(): void
    {
        $administrator = $this->user('Administrator');
        $project = $this->project($administrator, ['title' => 'Detailed Project']);
        $project->members()->attach($administrator, ['joined_at' => now(), 'is_active' => true]);

        $this->actingAs($administrator)->get(route('projects.show', $project))
            ->assertOk()->assertSee('Project Detail')->assertSee('Detailed Project')->assertSee($administrator->name);
    }

    private function user(string $role, ?Campus $campus = null): User
    {
        $user = User::factory()->create(['account_status' => 'active']);
        $user->assignRole($role);

        if ($campus) {
            StaffProfile::create([
                'user_id' => $user->id,
                'staff_number' => 'TEST-'.str_pad((string) $user->id, 6, '0', STR_PAD_LEFT),
                'campus_id' => $campus->id,
                'status' => 'active',
            ]);
        }

        return $user;
    }

    private function validPayload(User $creator, array $overrides = []): array
    {
        return array_merge([
            'title' => $creator->getRoleNames()->first().' Project',
            'project_category_id' => $this->category->id,
            'description' => 'Project description',
            'scope' => 'university_wide',
            'campus_ids' => [],
            'owner_id' => $creator->id,
            'member_ids' => [],
            'start_date' => '2026-09-01',
            'due_date' => '2026-12-01',
            'priority_level' => 'medium',
            'progress_method' => 'manual',
            'objectives' => 'Project objectives',
            'expected_deliverables' => 'Expected outputs',
        ], $overrides);
    }

    private function project(User $owner, array $overrides = []): Project
    {
        $this->projectSequence++;

        return Project::create(array_merge([
            'project_code' => 'PRJ-TEST-'.str_pad((string) $this->projectSequence, 4, '0', STR_PAD_LEFT),
            'title' => 'Test Project '.$this->projectSequence,
            'project_category_id' => $this->category->id,
            'owner_id' => $owner->id,
            'created_by' => $owner->id,
            'start_date' => '2026-09-01',
            'due_date' => '2026-12-01',
            'scope' => 'university_wide',
            'priority_level' => 'medium',
            'progress_method' => 'manual',
            'progress_percentage' => 0,
            'status' => 'planned',
            'is_active' => true,
        ], $overrides));
    }
}

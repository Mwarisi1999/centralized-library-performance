<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\User;
use Database\Seeders\ProjectCategorySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProjectEditingTest extends TestCase
{
    use RefreshDatabase;

    private Campus $firstCampus;

    private Campus $secondCampus;

    private ProjectCategory $category;

    private User $creator;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed([RolePermissionSeeder::class, ProjectCategorySeeder::class]);

        $this->firstCampus = Campus::create(['name' => 'First Campus', 'code' => 'FIRST', 'is_active' => true]);
        $this->secondCampus = Campus::create(['name' => 'Second Campus', 'code' => 'SECOND', 'is_active' => true]);
        $this->category = ProjectCategory::firstOrFail();
        $this->creator = $this->user('Administrator', 'Original Creator');
        $this->project = Project::create([
            'project_code' => 'PRJ-2026-0099',
            'title' => 'Original Project Title',
            'description' => 'Original description',
            'project_category_id' => $this->category->id,
            'owner_id' => $this->creator->id,
            'created_by' => $this->creator->id,
            'start_date' => '2026-09-01',
            'due_date' => '2026-12-01',
            'scope' => 'selected_campuses',
            'priority_level' => 'medium',
            'progress_method' => 'manual',
            'progress_percentage' => 0,
            'status' => 'planned',
            'objectives' => 'Original objectives',
            'expected_deliverables' => 'Original deliverables',
            'is_active' => true,
        ]);
        $this->project->campuses()->attach($this->firstCampus);
        $this->project->members()->attach($this->creator, ['joined_at' => now()->subDay(), 'is_active' => true]);
    }

    public function test_authorized_user_can_open_prepopulated_edit_page(): void
    {
        $this->actingAs($this->creator)->get(route('projects.edit', $this->project))
            ->assertOk()
            ->assertSee('Edit Project')
            ->assertSee('PRJ-2026-0099')
            ->assertSee('value="Original Project Title"', false)
            ->assertSee('value="2026-09-01"', false)
            ->assertSee('value="2026-12-01"', false)
            ->assertSee('value="'.$this->firstCampus->id.'"', false)
            ->assertSee('Update Project');
    }

    public function test_unauthorized_user_cannot_open_or_update_project_and_sees_no_edit_control(): void
    {
        $staff = $this->user('Staff', 'Restricted Staff');
        $this->project->members()->attach($staff, ['joined_at' => now(), 'is_active' => true]);

        $this->actingAs($staff)->get(route('projects.show', $this->project))
            ->assertOk()->assertDontSee('Edit Project');
        $this->actingAs($staff)->get(route('projects.edit', $this->project))->assertForbidden();
        $this->actingAs($staff)->patch(route('projects.update', $this->project), $this->payload())
            ->assertForbidden();
    }

    public function test_project_updates_and_immutable_fields_remain_unchanged(): void
    {
        $originalCode = $this->project->project_code;
        $originalCreator = $this->project->created_by;
        $originalCreatedAt = $this->project->created_at;

        $response = $this->actingAs($this->creator)->patch(route('projects.update', $this->project), $this->payload([
            'title' => 'Updated Project Title',
            'description' => 'Updated description',
            'priority_level' => 'critical',
            'progress_method' => 'deliverables',
            'objectives' => 'Updated objectives',
            'expected_deliverables' => 'Updated deliverables',
        ]));

        $response->assertRedirect(route('projects.show', $this->project))
            ->assertSessionHas('success', 'Project updated successfully.');

        $this->project->refresh();
        $this->assertSame('Updated Project Title', $this->project->title);
        $this->assertSame('critical', $this->project->priority_level);
        $this->assertSame('deliverables', $this->project->progress_method);
        $this->assertSame($originalCode, $this->project->project_code);
        $this->assertSame($originalCreator, $this->project->created_by);
        $this->assertTrue($originalCreatedAt->equalTo($this->project->created_at));

        $this->actingAs($this->creator)->get(route('projects.show', $this->project))
            ->assertOk()->assertSee('Updated Project Title')->assertSee('Project updated successfully.');
    }

    public function test_changing_owner_adds_new_owner_and_removes_old_owner_when_not_selected(): void
    {
        $newOwner = $this->user('University Librarian', 'New Owner');

        $this->actingAs($this->creator)->patch(route('projects.update', $this->project), $this->payload([
            'owner_id' => $newOwner->id,
            'member_ids' => [],
        ]))->assertRedirect(route('projects.show', $this->project));

        $this->assertDatabaseHas('projects', ['id' => $this->project->id, 'owner_id' => $newOwner->id]);
        $this->assertDatabaseHas('project_members', ['project_id' => $this->project->id, 'user_id' => $newOwner->id, 'is_active' => true]);
        $this->assertDatabaseHas('project_members', ['project_id' => $this->project->id, 'user_id' => $this->creator->id, 'is_active' => false]);
    }

    public function test_old_owner_remains_when_selected_and_members_add_remove_without_duplicates(): void
    {
        $newOwner = $this->user('University Librarian', 'New Owner');
        $removedMember = $this->user('Staff', 'Removed Member');
        $addedMember = $this->user('Staff', 'Added Member');
        $this->project->members()->attach($removedMember, ['joined_at' => now()->subDay(), 'is_active' => true]);

        $payload = $this->payload([
            'owner_id' => $newOwner->id,
            'member_ids' => [$this->creator->id, $addedMember->id],
        ]);

        $this->actingAs($this->creator)->patch(route('projects.update', $this->project), $payload)->assertRedirect();
        $this->actingAs($this->creator)->patch(route('projects.update', $this->project), $payload)->assertRedirect();

        foreach ([$newOwner, $this->creator, $addedMember] as $member) {
            $this->assertDatabaseHas('project_members', [
                'project_id' => $this->project->id, 'user_id' => $member->id, 'is_active' => true,
            ]);
            $this->assertSame(1, $this->project->projectMembers()->where('user_id', $member->id)->count());
        }
        $this->assertDatabaseHas('project_members', [
            'project_id' => $this->project->id, 'user_id' => $removedMember->id, 'is_active' => false,
        ]);
    }

    public function test_selected_campuses_can_change_and_scope_can_switch_both_directions(): void
    {
        $this->actingAs($this->creator)->patch(route('projects.update', $this->project), $this->payload([
            'campus_ids' => [$this->secondCampus->id],
        ]))->assertRedirect();
        $this->assertEquals([$this->secondCampus->id], $this->project->campuses()->pluck('campuses.id')->all());

        $this->actingAs($this->creator)->patch(route('projects.update', $this->project), $this->payload([
            'scope' => 'university_wide', 'campus_ids' => [],
        ]))->assertRedirect();
        $this->project->refresh();
        $this->assertSame('university_wide', $this->project->scope);
        $this->assertCount(0, $this->project->campuses);

        $this->actingAs($this->creator)->patch(route('projects.update', $this->project), $this->payload([
            'scope' => 'selected_campuses', 'campus_ids' => [$this->firstCampus->id, $this->secondCampus->id],
        ]))->assertRedirect();
        $this->project->refresh();
        $this->assertSame('selected_campuses', $this->project->scope);
        $this->assertEqualsCanonicalizing(
            [$this->firstCampus->id, $this->secondCampus->id],
            $this->project->campuses()->pluck('campuses.id')->all(),
        );
    }

    public function test_update_validation_requires_campus_and_rejects_invalid_due_date(): void
    {
        $this->actingAs($this->creator)->patch(route('projects.update', $this->project), $this->payload([
            'scope' => 'selected_campuses', 'campus_ids' => [],
            'start_date' => '2026-12-10', 'due_date' => '2026-12-09',
        ]))->assertSessionHasErrors(['campus_ids', 'due_date']);

        $this->assertDatabaseHas('projects', ['id' => $this->project->id, 'title' => 'Original Project Title']);
    }

    private function user(string $role, string $name): User
    {
        $user = User::factory()->create(['name' => $name, 'account_status' => 'active']);
        $user->assignRole($role);

        return $user;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Updated Project',
            'project_category_id' => $this->category->id,
            'description' => 'Updated description',
            'scope' => 'selected_campuses',
            'campus_ids' => [$this->firstCampus->id],
            'owner_id' => $this->creator->id,
            'member_ids' => [],
            'start_date' => '2026-09-01',
            'due_date' => '2027-01-31',
            'priority_level' => 'high',
            'progress_method' => 'manual',
            'objectives' => 'Updated objectives',
            'expected_deliverables' => 'Updated deliverables',
        ], $overrides);
    }
}

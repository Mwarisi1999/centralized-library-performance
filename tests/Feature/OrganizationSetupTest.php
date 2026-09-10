<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Position;
use App\Models\ProjectCategory;
use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationSetupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_administrator_can_open_default_page_without_search_parameter(): void
    {
        $this->actingAs($this->user('Administrator'))
            ->get('/admin/organization')
            ->assertOk()
            ->assertSee('Organization Setup');
    }

    public function test_search_filters_records_and_pagination_preserves_search(): void
    {
        $admin = $this->user('Administrator');
        foreach (range(1, 21) as $number) {
            Campus::create(['code' => 'MATCH-'.$number, 'name' => 'Search Match '.$number, 'is_active' => true]);
        }
        Campus::create(['code' => 'HIDDEN', 'name' => 'Unrelated Campus', 'is_active' => true]);

        $response = $this->actingAs($admin)->get(route('admin.organization.index', [
            'entity' => 'campuses', 'search' => 'Search Match',
        ]));

        $response->assertOk()->assertSee('Search Match 1')->assertDontSee('Unrelated Campus');
        $records = $response->viewData('records');
        $this->assertSame(21, $records->total());
        $this->assertStringContainsString('search=Search%20Match', $records->nextPageUrl());
    }

    public function test_empty_search_behaves_as_no_search(): void
    {
        $campus = Campus::create(['code' => 'MAIN', 'name' => 'Main Campus', 'is_active' => true]);

        $this->actingAs($this->user('Administrator'))
            ->get(route('admin.organization.index', ['entity' => 'campuses', 'search' => '   ']))
            ->assertOk()
            ->assertSee($campus->name);
    }

    public function test_oversized_and_non_string_search_values_are_rejected(): void
    {
        $admin = $this->user('Administrator');

        $this->actingAs($admin)->get(route('admin.organization.index', ['entity' => 'campuses', 'search' => str_repeat('a', 256)]))
            ->assertSessionHasErrors('search');
        $this->actingAs($admin)->get(route('admin.organization.index', ['entity' => 'campuses', 'search' => ['invalid']]))
            ->assertSessionHasErrors('search');
    }

    public function test_all_supported_entity_tabs_render_and_project_category_search_uses_valid_columns(): void
    {
        $admin = $this->user('Administrator');
        ProjectCategory::create(['name' => 'Digital Scholarship', 'description' => 'Repository and digitization work.', 'is_active' => true]);

        foreach (['campuses', 'libraries', 'positions', 'project-categories'] as $entity) {
            $this->actingAs($admin)->get(route('admin.organization.index', $entity))->assertOk();
        }

        $this->actingAs($admin)->get(route('admin.organization.index', [
            'entity' => 'project-categories', 'search' => 'digitization',
        ]))->assertOk()->assertSee('Digital Scholarship');
    }

    public function test_non_administrator_is_denied(): void
    {
        $this->actingAs($this->user('Staff'))
            ->get(route('admin.organization.index'))
            ->assertForbidden();
    }

    public function test_create_edit_validation_and_non_destructive_lifecycle_still_work(): void
    {
        $admin = $this->user('Administrator');
        $this->actingAs($admin)->get(route('admin.organization.create', 'positions'))->assertOk();
        $this->actingAs($admin)->post(route('admin.organization.store', 'positions'), [
            'name' => 'Systems Librarian', 'code' => 'SL', 'description' => 'Systems role.', 'is_active' => 1,
        ])->assertRedirect(route('admin.organization.index', 'positions'));

        $position = Position::where('code', 'SL')->firstOrFail();
        $this->actingAs($admin)->get(route('admin.organization.edit', ['positions', $position->id]))->assertOk();
        $this->actingAs($admin)->patch(route('admin.organization.update', ['positions', $position->id]), [
            'name' => '', 'code' => 'SL', 'is_active' => 1,
        ])->assertSessionHasErrors('name');

        $this->actingAs($admin)->patch(route('admin.organization.update', ['positions', $position->id]), [
            'name' => $position->name, 'code' => $position->code, 'is_active' => 0,
        ])->assertRedirect();
        $this->assertFalse($position->fresh()->is_active);
        $position->update(['is_active' => true]);

        StaffProfile::create(['user_id' => $this->user('Staff')->id, 'staff_number' => 'ORG-0001', 'position_id' => $position->id, 'status' => 'active']);
        $this->actingAs($admin)->patch(route('admin.organization.toggle', ['positions', $position->id]))->assertRedirect();

        $this->assertDatabaseHas('positions', ['id' => $position->id, 'is_active' => false, 'deleted_at' => null]);
        $this->assertDatabaseHas('staff_profiles', ['position_id' => $position->id]);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['account_status' => 'active', 'email_verified_at' => now()]);
        $user->assignRole($role);

        return $user;
    }
}

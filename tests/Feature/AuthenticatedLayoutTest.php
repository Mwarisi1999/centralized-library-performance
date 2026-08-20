<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AuthenticatedLayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_administrator_sees_management_and_future_navigation_allowed_by_permissions(): void
    {
        $user = $this->userWithRole('Administrator');

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Staff Management')
            ->assertSee('Organization Setup')
            ->assertSee('Administration')
            ->assertSee('Projects')
            ->assertSee('Tasks')
            ->assertSee('Reports')
            ->assertSee('Evidence');
    }

    public function test_staff_sees_only_permitted_work_navigation(): void
    {
        $user = $this->userWithRole('Staff');

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('My Work')
            ->assertSee('Projects')
            ->assertSee('Tasks')
            ->assertSee('Reports')
            ->assertSee('Evidence')
            ->assertDontSee('Staff Management')
            ->assertDontSee('Organization Setup')
            ->assertDontSee('Administration');
    }

    public function test_monitoring_officer_sees_monitoring_navigation_but_not_administration(): void
    {
        $user = $this->userWithRole('M&E Officer');

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Staff Management')
            ->assertSee('Projects')
            ->assertSee('Tasks')
            ->assertSee('Reports')
            ->assertSee('Evidence')
            ->assertDontSee('Organization Setup')
            ->assertDontSee('Administration');
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['account_status' => 'active']);
        $user->assignRole($role);

        return $user;
    }
}

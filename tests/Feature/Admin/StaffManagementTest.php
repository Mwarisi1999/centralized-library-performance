<?php

namespace Tests\Feature\Admin;

use App\Models\Campus;
use App\Models\Library;
use App\Models\Position;
use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class StaffManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_guest_and_user_without_permission_cannot_access_staff_management(): void
    {
        $this->get(route('admin.staff.index'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())
            ->get(route('admin.staff.index'))
            ->assertForbidden();
    }

    public function test_administrator_can_view_staff_management_and_staff_details(): void
    {
        $administrator = User::factory()->create(['account_status' => 'active']);
        $administrator->assignRole('Administrator');

        [$member, $campus, $library] = $this->createStaffMember();

        $this->actingAs($administrator)
            ->get(route('admin.staff.index'))
            ->assertOk()
            ->assertSee('Staff Management')
            ->assertSee($member->name)
            ->assertSee('LIB-000001')
            ->assertSee($campus->name)
            ->assertSee($library->name)
            ->assertSee('Staff')
            ->assertSee('Pending');
    }

    public function test_search_finds_staff_by_name_email_staff_number_and_phone(): void
    {
        $administrator = $this->administrator();
        [$member] = $this->createStaffMember();

        foreach (['Jane', $member->email, 'LIB-000001', '0700123456'] as $search) {
            $this->actingAs($administrator)
                ->get(route('admin.staff.index', ['search' => $search]))
                ->assertOk()
                ->assertSee($member->name);
        }

        $this->actingAs($administrator)
            ->get(route('admin.staff.index', ['search' => 'does-not-exist']))
            ->assertSee('No staff members found.');
    }

    public function test_filters_work_individually_and_in_combination(): void
    {
        $administrator = $this->administrator();
        [$matching, $campus, , $position] = $this->createStaffMember();

        $other = User::factory()->create([
            'name' => 'Other Intern',
            'account_status' => 'active',
        ]);
        $other->assignRole('Intern');

        foreach ([
            ['campus_id' => $campus->id],
            ['role' => 'Staff'],
            ['account_status' => 'pending'],
            [
                'campus_id' => $campus->id,
                'role' => 'Staff',
                'position_id' => $position->id,
                'employment_type' => 'permanent',
                'account_status' => 'pending',
            ],
        ] as $filters) {
            $this->actingAs($administrator)
                ->get(route('admin.staff.index', $filters))
                ->assertOk()
                ->assertSee($matching->name)
                ->assertDontSee($other->name);
        }
    }

    public function test_pagination_preserves_search_and_filters(): void
    {
        $administrator = $this->administrator();
        $campus = Campus::create(['name' => 'Main Campus', 'code' => 'MAIN', 'is_active' => true]);

        for ($index = 1; $index <= 16; $index++) {
            $member = User::factory()->create(['name' => "Library Staff {$index}"]);
            $member->assignRole('Staff');
            StaffProfile::create([
                'user_id' => $member->id,
                'staff_number' => 'LIB-'.str_pad((string) $index, 6, '0', STR_PAD_LEFT),
                'campus_id' => $campus->id,
                'employment_type' => 'permanent',
                'status' => 'active',
            ]);
        }

        $this->actingAs($administrator)
            ->get(route('admin.staff.index', [
                'search' => 'Library Staff',
                'campus_id' => $campus->id,
                'role' => 'Staff',
            ]))
            ->assertOk()
            ->assertSee('search=Library%20Staff')
            ->assertSee('campus_id='.$campus->id)
            ->assertSee('role=Staff');
    }

    public function test_administrator_can_view_profile_and_user_without_permission_cannot(): void
    {
        $administrator = $this->administrator();
        [$member] = $this->createStaffMember();

        $this->actingAs($administrator)
            ->get(route('admin.staff.show', $member))
            ->assertOk()
            ->assertSee('Staff Profile')
            ->assertSee($member->name)
            ->assertSee('LIB-000001');

        $this->actingAs(User::factory()->create())
            ->get(route('admin.staff.show', $member))
            ->assertForbidden();
    }

    public function test_profile_handles_system_user_without_staff_profile(): void
    {
        $administrator = $this->administrator();

        $this->actingAs($administrator)
            ->get(route('admin.staff.show', $administrator))
            ->assertOk()
            ->assertSee('University-wide')
            ->assertDontSee('two_factor_secret')
            ->assertDontSee('remember_token');
    }

    public function test_profile_displays_supervisor_and_database_direct_reports(): void
    {
        $administrator = $this->administrator();
        $supervisor = User::factory()->create(['name' => 'Campus Supervisor']);
        $supervisor->assignRole('Campus Librarian');
        [$member] = $this->createStaffMember();
        $member->staffProfile()->update(['supervisor_id' => $supervisor->id]);

        $this->actingAs($administrator)
            ->get(route('admin.staff.show', $member))
            ->assertOk()
            ->assertSee('Campus Supervisor')
            ->assertSee('Reports To');

        $this->actingAs($administrator)
            ->get(route('admin.staff.show', $supervisor))
            ->assertOk()
            ->assertSee('Direct Reports')
            ->assertSee($member->name)
            ->assertSee('LIB-000001');
    }

    public function test_edit_form_is_prepopulated_and_update_preserves_staff_number(): void
    {
        $administrator = $this->administrator();
        [$member, $campus, $library, $position] = $this->createStaffMember();

        $this->actingAs($administrator)
            ->get(route('admin.staff.edit', $member))
            ->assertOk()
            ->assertSee('Edit Staff Account')
            ->assertSee('value="Jane Library"', false)
            ->assertSee('LIB-000001');

        $this->actingAs($administrator)
            ->put(route('admin.staff.update', $member), [
                'name' => 'Jane Updated',
                'email' => $member->email,
                'phone' => '0700999999',
                'role' => 'Staff',
                'campus_id' => $campus->id,
                'library_id' => $library->id,
                'position_id' => $position->id,
                'employment_type' => 'contract',
                'start_date' => '2026-08-01',
                'account_status' => 'active',
            ])
            ->assertRedirect(route('admin.staff.show', $member));

        $this->assertDatabaseHas('users', [
            'id' => $member->id,
            'name' => 'Jane Updated',
            'account_status' => 'active',
        ]);
        $this->assertDatabaseHas('staff_profiles', [
            'user_id' => $member->id,
            'staff_number' => 'LIB-000001',
            'phone' => '0700999999',
            'employment_type' => 'contract',
        ]);
    }

    public function test_update_rejects_duplicate_email_inconsistent_library_and_self_supervision(): void
    {
        $administrator = $this->administrator();
        [$member, $campus, , $position] = $this->createStaffMember();
        $other = User::factory()->create();
        $otherCampus = Campus::create(['name' => 'Arapai Campus', 'code' => 'ARAP', 'is_active' => true]);
        $otherLibrary = Library::create([
            'campus_id' => $otherCampus->id,
            'name' => 'Arapai Library',
            'code' => 'AL',
            'is_active' => true,
        ]);

        $payload = [
            'name' => $member->name,
            'email' => $other->email,
            'role' => 'Staff',
            'campus_id' => $campus->id,
            'library_id' => $otherLibrary->id,
            'position_id' => $position->id,
            'supervisor_id' => $member->id,
            'employment_type' => 'permanent',
            'account_status' => 'pending',
        ];

        $this->actingAs($administrator)
            ->put(route('admin.staff.update', $member), $payload)
            ->assertSessionHasErrors(['email', 'library_id', 'supervisor_id']);
    }

    public function test_valid_campus_librarian_can_be_assigned_as_staff_supervisor(): void
    {
        $administrator = $this->administrator();
        [$member, $campus, $library, $position] = $this->createStaffMember();
        $supervisor = User::factory()->create(['name' => 'Main Campus Librarian']);
        $supervisor->assignRole('Campus Librarian');
        StaffProfile::create([
            'user_id' => $supervisor->id,
            'staff_number' => 'LIB-000002',
            'campus_id' => $campus->id,
            'library_id' => $library->id,
            'status' => 'active',
        ]);

        $this->actingAs($administrator)
            ->put(route('admin.staff.update', $member), [
                'name' => $member->name,
                'email' => $member->email,
                'role' => 'Staff',
                'campus_id' => $campus->id,
                'library_id' => $library->id,
                'position_id' => $position->id,
                'supervisor_id' => $supervisor->id,
                'employment_type' => 'permanent',
                'account_status' => 'pending',
            ])
            ->assertRedirect(route('admin.staff.show', $member));

        $this->assertDatabaseHas('staff_profiles', [
            'user_id' => $member->id,
            'supervisor_id' => $supervisor->id,
            'staff_number' => 'LIB-000001',
        ]);
    }

    public function test_administrator_without_profile_can_be_updated_and_unauthorized_user_is_forbidden(): void
    {
        $administrator = $this->administrator();
        $unauthorized = User::factory()->create();

        $this->actingAs($administrator)
            ->put(route('admin.staff.update', $administrator), [
                'name' => 'System Administrator',
                'email' => $administrator->email,
                'role' => 'Administrator',
                'account_status' => 'active',
            ])
            ->assertRedirect(route('admin.staff.show', $administrator));

        $this->assertDatabaseMissing('staff_profiles', ['user_id' => $administrator->id]);

        $this->actingAs($unauthorized)
            ->get(route('admin.staff.edit', $administrator))
            ->assertForbidden();

        $this->actingAs($unauthorized)
            ->put(route('admin.staff.update', $administrator), [
                'name' => 'Unauthorized Change',
                'email' => $administrator->email,
                'role' => 'Administrator',
                'account_status' => 'active',
            ])
            ->assertForbidden();
    }

    private function administrator(): User
    {
        $administrator = User::factory()->create(['account_status' => 'active']);
        $administrator->assignRole('Administrator');

        return $administrator;
    }

    private function createStaffMember(): array
    {
        $campus = Campus::create(['name' => 'Main Campus', 'code' => 'MAIN', 'is_active' => true]);
        $library = Library::create([
            'campus_id' => $campus->id,
            'name' => 'Main Library',
            'code' => 'ML',
            'is_active' => true,
        ]);
        $position = Position::create(['name' => 'Library Assistant', 'code' => 'LA', 'is_active' => true]);
        $member = User::factory()->create([
            'name' => 'Jane Library',
            'email' => 'jane.library@example.test',
            'account_status' => 'pending',
        ]);
        $member->assignRole('Staff');
        StaffProfile::create([
            'user_id' => $member->id,
            'staff_number' => 'LIB-000001',
            'campus_id' => $campus->id,
            'library_id' => $library->id,
            'position_id' => $position->id,
            'phone' => '0700123456',
            'employment_type' => 'permanent',
            'status' => 'active',
        ]);

        return [$member, $campus, $library, $position];
    }
}

<?php

namespace Tests\Feature;

use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_staff_and_intern_can_open_their_profile(): void
    {
        foreach (['Staff', 'Intern'] as $role) {
            $user = $this->profileUser($role);

            $this->actingAs($user)->get(route('profile.show'))
                ->assertOk()
                ->assertSee('My Profile')
                ->assertSee($user->staffProfile->staff_number);
        }
    }

    public function test_other_authenticated_roles_can_open_their_profile(): void
    {
        $user = User::factory()->create(['account_status' => 'active']);
        $user->assignRole('M&E Officer');

        $this->actingAs($user)->get(route('profile.show'))
            ->assertOk()
            ->assertSee('My Profile');
    }

    public function test_user_can_update_personal_details_without_changing_employment_assignment(): void
    {
        $user = $this->profileUser('Staff');
        $staffNumber = $user->staffProfile->staff_number;

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => 'Updated Staff Member',
            'email' => 'updated.staff@example.test',
            'phone' => '+256 700 123456',
            'gender' => 'female',
            'date_of_birth' => '1995-04-12',
            'address' => 'Busitema, Uganda',
            'emergency_contact_name' => 'Contact Person',
            'emergency_contact_phone' => '+256 701 654321',
            'emergency_contact_relationship' => 'Sibling',
            'staff_number' => 'ILLEGAL-CHANGE',
        ])->assertRedirect(route('profile.show'));

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Updated Staff Member']);
        $this->assertDatabaseHas('staff_profiles', [
            'user_id' => $user->id,
            'staff_number' => $staffNumber,
            'phone' => '+256 700 123456',
            'emergency_contact_name' => 'Contact Person',
        ]);
    }

    public function test_profile_picture_uses_the_public_disk_and_renders_for_its_owner(): void
    {
        Storage::fake('public');
        $user = $this->profileUser('Intern');

        $this->actingAs($user)->patch(route('profile.picture.update'), [
            'profile_picture' => UploadedFile::fake()->createWithContent(
                'portrait.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true),
            ),
        ])->assertRedirect(route('profile.show'));

        $path = $user->fresh()->profile_picture;
        Storage::disk('public')->assertExists($path);
        $this->actingAs($user)->get(route('profile.show'))
            ->assertOk()
            ->assertSee('/storage/'.$path, false);
    }

    public function test_user_can_change_password_with_current_password(): void
    {
        $user = $this->profileUser('Staff');

        $this->actingAs($user)->patch(route('profile.password.update'), [
            'current_password' => 'password',
            'password' => 'A-strong-new-password-2026',
            'password_confirmation' => 'A-strong-new-password-2026',
        ])->assertRedirect(route('profile.show'));

        $this->assertTrue(Hash::check('A-strong-new-password-2026', $user->fresh()->password));
    }

    private function profileUser(string $role): User
    {
        $user = User::factory()->create(['account_status' => 'active']);
        $user->assignRole($role);
        StaffProfile::create([
            'user_id' => $user->id,
            'staff_number' => strtoupper($role).'-'.$user->id,
            'employment_type' => $role === 'Intern' ? 'intern' : 'permanent',
            'status' => 'active',
        ]);

        return $user->load('staffProfile');
    }
}

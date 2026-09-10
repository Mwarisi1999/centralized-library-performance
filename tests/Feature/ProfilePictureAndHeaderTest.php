<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\WorkflowNotification;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfilePictureAndHeaderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_authenticated_user_can_open_profile_page(): void
    {
        $this->actingAs($this->activeUser())
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Profile picture')
            ->assertSee('Personal information');
    }

    public function test_valid_profile_picture_is_stored_and_persisted(): void
    {
        $user = $this->activeUser();

        $this->actingAs($user)->patch(route('profile.picture.update'), [
            'profile_picture' => $this->png('portrait.png'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $path = $user->fresh()->profile_picture;

        $this->assertNotNull($path);
        $this->assertStringStartsWith('profile-pictures/', $path);
        $this->assertNotSame('profile-pictures/portrait.png', $path);
        Storage::disk('public')->assertExists($path);
        $this->assertSame('/storage/'.$path, $user->fresh()->profilePictureUrl());

        $this->actingAs($user)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('/storage/'.$path, false)
            ->assertSee('Dorothy Inzikuru profile picture');
    }

    public function test_invalid_and_oversized_files_are_rejected(): void
    {
        $user = $this->activeUser();

        $this->actingAs($user)->patch(route('profile.picture.update'), [
            'profile_picture' => UploadedFile::fake()->createWithContent('notes.txt', 'not an image'),
        ])->assertSessionHasErrors('profile_picture');

        $this->actingAs($user)->patch(route('profile.picture.update'), [
            'profile_picture' => $this->png('oversized.png', 2049),
        ])->assertSessionHasErrors('profile_picture');

        $this->assertNull($user->fresh()->profile_picture);
        Storage::disk('public')->assertDirectoryEmpty('profile-pictures');
    }

    public function test_profile_picture_can_be_replaced_without_leaving_the_old_file(): void
    {
        $user = $this->activeUser();

        $this->actingAs($user)->patch(route('profile.picture.update'), [
            'profile_picture' => $this->png('first.png'),
        ]);
        $oldPath = $user->fresh()->profile_picture;

        $this->actingAs($user)->patch(route('profile.picture.update'), [
            'profile_picture' => $this->png('second.png'),
        ])->assertSessionHasNoErrors();
        $newPath = $user->fresh()->profile_picture;

        $this->assertNotSame($oldPath, $newPath);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($newPath);
    }

    public function test_profile_picture_can_be_removed(): void
    {
        $user = $this->activeUser();
        $this->actingAs($user)->patch(route('profile.picture.update'), [
            'profile_picture' => $this->png('portrait.png'),
        ]);
        $path = $user->fresh()->profile_picture;

        $this->actingAs($user)->delete(route('profile.picture.destroy'))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertNull($user->fresh()->profile_picture);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_user_can_only_change_their_own_profile_picture(): void
    {
        $owner = $this->activeUser();
        $attacker = $this->activeUser();

        $this->actingAs($attacker)->patch(route('profile.picture.update', ['user_id' => $owner->id]), [
            'user_id' => $owner->id,
            'profile_picture' => $this->png('attacker.png'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertNull($owner->fresh()->profile_picture);
        $this->assertNotNull($attacker->fresh()->profile_picture);
    }

    public function test_user_cannot_remove_another_users_profile_picture(): void
    {
        $owner = $this->activeUser();
        $attacker = $this->activeUser();
        $this->actingAs($owner)->patch(route('profile.picture.update'), [
            'profile_picture' => $this->png('owner.png'),
        ]);
        $ownerPath = $owner->fresh()->profile_picture;

        $this->actingAs($attacker)->delete(route('profile.picture.destroy', ['user_id' => $owner->id]))
            ->assertRedirect();

        $this->assertSame($ownerPath, $owner->fresh()->profile_picture);
        Storage::disk('public')->assertExists($ownerPath);
    }

    public function test_updating_contact_information_retains_existing_picture(): void
    {
        $user = $this->activeUser();
        $this->actingAs($user)->patch(route('profile.picture.update'), [
            'profile_picture' => $this->png('portrait.png'),
        ]);
        $path = $user->fresh()->profile_picture;

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => 'Updated Person',
            'email' => 'updated.person@example.test',
            'phone' => '',
        ])->assertSessionHasNoErrors();

        $this->assertSame($path, $user->fresh()->profile_picture);
        Storage::disk('public')->assertExists($path);
    }

    public function test_header_uses_picture_when_available_and_initials_as_fallback(): void
    {
        $withPicture = $this->activeUser('Dorothy Inzikuru');
        $this->actingAs($withPicture)->patch(route('profile.picture.update'), [
            'profile_picture' => $this->png('portrait.png'),
        ]);
        $path = $withPicture->fresh()->profile_picture;

        $this->actingAs($withPicture)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('/storage/'.$path, false)
            ->assertSee('Dorothy Inzikuru profile picture');

        $withoutPicture = $this->activeUser('Isaac Mukungu');
        $this->actingAs($withoutPicture)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('IM')
            ->assertDontSee('Isaac Mukungu profile picture');
    }

    public function test_missing_physical_picture_falls_back_to_initials_without_rendering_an_image(): void
    {
        $user = $this->activeUser('Mwarisi Brian Arthur');
        $user->update(['profile_picture' => 'profile-pictures/missing.jpg']);

        $this->actingAs($user)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('MBA')
            ->assertDontSee('Mwarisi Brian Arthur profile picture');
    }

    public function test_header_contains_current_kampala_date_and_notification_bell(): void
    {
        $this->travelTo(now('Africa/Kampala')->setDate(2026, 9, 9)->setTime(10, 30));
        $user = $this->activeUser();
        $user->notify(new WorkflowNotification([
            'event' => 'header_test',
            'title' => 'Header notification',
            'message' => 'Notification bell remains available.',
            'url' => route('dashboard'),
            'severity' => 'info',
            'unique_key' => 'header-test',
        ]));

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Wed, 09 Sep 2026')
            ->assertSee('aria-label="Notifications"', false)
            ->assertSee('Header notification');

        $this->travelBack();
    }

    private function activeUser(string $name = 'Dorothy Inzikuru'): User
    {
        $user = User::factory()->create(['name' => $name, 'account_status' => 'active']);
        $user->assignRole('Staff');

        return $user;
    }

    private function png(string $name, ?int $kilobytes = null): UploadedFile
    {
        $image = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);

        if ($kilobytes !== null) {
            $image .= str_repeat("\0", ($kilobytes * 1024) - strlen($image));
        }

        return UploadedFile::fake()->createWithContent($name, $image);
    }
}

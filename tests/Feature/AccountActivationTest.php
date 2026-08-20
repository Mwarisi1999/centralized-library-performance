<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Library;
use App\Models\User;
use App\Notifications\StaffAccountInvitation;
use App\Services\AccountInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AccountActivationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::create(['name' => 'create staff']);

        foreach (['Administrator', 'Campus Librarian', 'Staff', 'Intern'] as $role) {
            Role::create(['name' => $role]);
        }

        Role::findByName('Administrator')->givePermissionTo('create staff');
    }

    public function test_only_active_users_can_log_in(): void
    {
        foreach ([
            'pending' => 'Your account has not yet been activated.',
            'suspended' => 'Your account has been suspended.',
            'inactive' => 'Your account is inactive.',
        ] as $status => $message) {
            $user = User::factory()->create([
                'email' => $status.'@example.test',
                'password' => Hash::make('ValidPassword1!'),
                'account_status' => $status,
            ]);

            $this->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'ValidPassword1!',
            ])->assertSessionHasErrors('email');

            $this->assertGuest();
        }

        $active = User::factory()->create([
            'email' => 'active@example.test',
            'password' => Hash::make('ValidPassword1!'),
            'account_status' => 'active',
        ]);

        $this->post(route('login.store'), [
            'email' => $active->email,
            'password' => 'ValidPassword1!',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($active);
    }

    public function test_staff_creation_generates_token_and_sends_invitation_without_password(): void
    {
        Notification::fake();
        $administrator = $this->administrator();
        $campus = Campus::create(['name' => 'Main Campus', 'code' => 'MAIN', 'is_active' => true]);
        $library = Library::create([
            'campus_id' => $campus->id,
            'name' => 'Main Library',
            'code' => 'ML',
            'is_active' => true,
        ]);

        $this->actingAs($administrator)->post(route('admin.staff.store'), [
            'name' => 'Invited Staff',
            'email' => 'invited@example.test',
            'role' => 'Staff',
            'campus_id' => $campus->id,
            'library_id' => $library->id,
            'employment_type' => 'permanent',
        ])->assertRedirect(route('admin.staff.create'));

        $user = User::where('email', 'invited@example.test')->firstOrFail();

        $this->assertNull($user->password);
        $this->assertSame('pending', $user->account_status);
        $this->assertDatabaseHas('account_activation_tokens', ['user_id' => $user->id, 'used_at' => null]);
        Notification::assertSentTo($user, StaffAccountInvitation::class);
    }

    public function test_valid_token_opens_page_and_activation_updates_account_once(): void
    {
        [$user, $token] = $this->invitedUserAndPlainToken();

        $this->get(route('account.activate', $token))
            ->assertOk()
            ->assertSee('Activate your account')
            ->assertSee($user->email);

        $this->post(route('account.activate.store', $token), [
            'password' => 'StrongPassword1!',
            'password_confirmation' => 'StrongPassword1!',
        ])
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Your account has been activated successfully. You can now sign in.');

        $user->refresh();
        $this->assertTrue(Hash::check('StrongPassword1!', $user->password));
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame('active', $user->account_status);
        $this->assertNotNull($user->activated_at);
        $this->assertNotNull($user->activationTokens()->firstOrFail()->used_at);

        $this->get(route('account.activate', $token))->assertNotFound();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'StrongPassword1!',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_expired_used_and_invalid_tokens_are_rejected(): void
    {
        [$user, $token] = $this->invitedUserAndPlainToken();
        $user->activationTokens()->update(['expires_at' => now()->subMinute()]);
        $this->get(route('account.activate', $token))->assertNotFound();

        [$usedUser, $usedToken] = $this->invitedUserAndPlainToken('used@example.test');
        $usedUser->activationTokens()->update(['used_at' => now()]);
        $this->get(route('account.activate', $usedToken))->assertNotFound();

        $this->get(route('account.activate', 'invalid-token'))->assertNotFound();
    }

    public function test_resend_replaces_old_token_and_active_account_is_rejected(): void
    {
        Notification::fake();
        $administrator = $this->administrator();
        [$user] = $this->invitedUserAndPlainToken();
        $oldHash = $user->activationTokens()->firstOrFail()->token_hash;

        $this->actingAs($administrator)
            ->post(route('admin.staff.resend-invitation', $user))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('account_activation_tokens', ['token_hash' => $oldHash]);
        $this->assertSame(1, $user->activationTokens()->count());
        $this->assertNotSame($oldHash, $user->activationTokens()->first()->token_hash);

        $user->update(['account_status' => 'active']);
        $this->actingAs($administrator)
            ->post(route('admin.staff.resend-invitation', $user))
            ->assertSessionHasErrors('invitation');
    }

    public function test_administrator_can_log_in_and_registration_routes_do_not_exist(): void
    {
        $administrator = $this->administrator();

        $this->post(route('login.store'), [
            'email' => $administrator->email,
            'password' => 'Administrator1!',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($administrator);
        $this->get('/register')->assertNotFound();
        $this->post('/register')->assertNotFound();
    }

    private function administrator(): User
    {
        $administrator = User::factory()->create([
            'password' => Hash::make('Administrator1!'),
            'account_status' => 'active',
            'email_verified_at' => now(),
            'activated_at' => now(),
        ]);
        $administrator->assignRole('Administrator');

        return $administrator;
    }

    private function invitedUserAndPlainToken(string $email = 'pending@example.test'): array
    {
        Notification::fake();
        $user = User::factory()->create([
            'email' => $email,
            'password' => null,
            'account_status' => 'pending',
        ]);

        app(AccountInvitationService::class)->send($user);
        $notification = Notification::sent($user, StaffAccountInvitation::class)->first();
        $url = $notification->toMail($user)->actionUrl;
        $token = basename(parse_url($url, PHP_URL_PATH));

        return [$user, $token];
    }
}

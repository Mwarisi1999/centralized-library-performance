<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\WorkflowNotification;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationDashboardRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_dashboard_loads_when_authenticated_user_has_zero_notifications(): void
    {
        $user = $this->activeStaff();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()->assertSee('Welcome');
        $this->assertCount(0, $response->viewData('notifications'));
    }

    public function test_dashboard_loads_and_displays_an_unread_notification(): void
    {
        $user = $this->activeStaff();
        $user->notify($this->notification('Unread dashboard notice'));

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()->assertSee('Unread dashboard notice');
        $this->assertCount(1, $response->viewData('notifications'));
        $this->assertCount(1, $user->fresh()->unreadNotifications);
    }

    public function test_read_notifications_are_excluded_from_dashboard_unread_collection(): void
    {
        $user = $this->activeStaff();
        $user->notify($this->notification('Already read notice'));
        $user->notifications()->firstOrFail()->markAsRead();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $this->assertCount(0, $response->viewData('notifications'));
        $this->assertCount(0, $user->fresh()->unreadNotifications);
        $this->assertCount(1, $user->fresh()->notifications);
    }

    public function test_another_users_notifications_are_not_exposed_on_dashboard(): void
    {
        $viewer = $this->activeStaff();
        $other = $this->activeStaff();
        $other->notify($this->notification('Private other-user notice'));

        $response = $this->actingAs($viewer)->get(route('dashboard'));

        $response->assertOk()->assertDontSee('Private other-user notice');
        $this->assertCount(0, $response->viewData('notifications'));
    }

    public function test_user_cannot_mark_another_users_notification_as_read(): void
    {
        $owner = $this->activeStaff();
        $attacker = $this->activeStaff();
        $owner->notify($this->notification('Protected notice'));
        $notification = $owner->notifications()->firstOrFail();

        $this->actingAs($attacker)->post(route('notifications.read', $notification))->assertForbidden();

        $this->assertNull($notification->fresh()->read_at);
    }

    private function activeStaff(): User
    {
        $user = User::factory()->create(['account_status' => 'active']);
        $user->assignRole('Staff');

        return $user;
    }

    private function notification(string $title): WorkflowNotification
    {
        return new WorkflowNotification([
            'event' => 'regression_test',
            'title' => $title,
            'message' => 'Notification regression coverage.',
            'url' => route('dashboard'),
            'severity' => 'info',
            'unique_key' => str($title)->slug()->toString(),
        ]);
    }
}

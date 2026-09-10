<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PersonalWorkNavigationTest extends TestCase
{
    use RefreshDatabase;

    private const COMMON_PERSONAL_MENU = [
        'Daily Activities',
        'Weekly Activities',
        'Notifications',
        'My Profile',
        'Projects',
        'Task Tracker',
        'Evidence',
        'Reports',
        'Printable Timesheet',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_each_operational_role_sees_the_standard_personal_menu_without_legacy_labels(): void
    {
        foreach (['Staff', 'Intern', 'M&E Officer', 'Campus Librarian', 'University Librarian', 'Administrator'] as $role) {
            $response = $this->actingAs($this->user($role))->get(route('dashboard'))->assertOk();
            $labels = $this->sidebarLabels($response);

            $this->assertMenuOrder($labels, self::COMMON_PERSONAL_MENU, $role);
            $this->assertNotContains('My Work', $labels, "The {$role} sidebar still contains My Work.");
            $this->assertNotContains('Tasks', $labels, "The {$role} sidebar still contains the legacy Tasks label.");
        }
    }

    public function test_role_specific_navigation_remains_available_alongside_the_common_menu(): void
    {
        $expectations = [
            'M&E Officer' => ['Reports Awaiting My Review', 'Staff Management'],
            'Campus Librarian' => ['Campus Dashboard', 'Campus Monthly Reports', 'Reports Awaiting My Review'],
            'University Librarian' => ['University Dashboard', 'Reports Awaiting My Review', 'Staff Management'],
            'Administrator' => ['Staff Management', 'Organization Setup', 'Administration'],
        ];

        foreach ($expectations as $role => $expectedLabels) {
            $labels = $this->sidebarLabels(
                $this->actingAs($this->user($role))->get(route('dashboard'))->assertOk()
            );

            foreach ($expectedLabels as $label) {
                $this->assertContains($label, $labels, "The {$role} sidebar is missing {$label}.");
            }
        }
    }

    public function test_common_personal_routes_have_one_correct_active_sidebar_item(): void
    {
        $user = $this->user('Staff');
        $routes = [
            'daily-activities.index' => 'Daily Activities',
            'weekly-activities.index' => 'Weekly Activities',
            'notifications.index' => 'Notifications',
            'profile.show' => 'My Profile',
            'projects.index' => 'Projects',
            'task-tracker.index' => 'Task Tracker',
            'my-work.monthly-report' => 'Reports',
            'printable-timesheet.index' => 'Printable Timesheet',
        ];

        foreach ($routes as $route => $expectedLabel) {
            $response = $this->actingAs($user)->get(route($route))->assertOk();

            $this->assertSame([$expectedLabel], $this->activeSidebarLabels($response));
        }
    }

    /** @return array<int, string> */
    private function sidebarLabels(TestResponse $response): array
    {
        preg_match_all('/<span data-sidebar-label[^>]*>([^<]+)<\/span>/', $response->getContent(), $matches);

        return array_map('trim', $matches[1]);
    }

    /** @return array<int, string> */
    private function activeSidebarLabels(TestResponse $response): array
    {
        preg_match_all('/<a(?=[^>]*aria-current="page")[^>]*>.*?<span data-sidebar-label[^>]*>([^<]+)<\/span>.*?<\/a>/s', $response->getContent(), $matches);

        return array_map('trim', $matches[1]);
    }

    /** @param array<int, string> $actual @param array<int, string> $expected */
    private function assertMenuOrder(array $actual, array $expected, string $role): void
    {
        $positions = array_map(fn (string $label) => array_search($label, $actual, true), $expected);

        foreach ($positions as $position) {
            $this->assertNotSame(false, $position, "The {$role} sidebar is missing a common personal menu item.");
        }
        $this->assertSame($positions, collect($positions)->sort()->values()->all(), "The {$role} personal menu order is inconsistent.");
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['account_status' => 'active']);
        $user->assignRole($role);

        return $user;
    }
}

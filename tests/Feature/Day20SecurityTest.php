<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\MonthlyReport;
use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Day20SecurityTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_staff_direct_url_authorization_matrix_is_server_side_enforced(): void
    {
        $staff = $this->staff($this->campus('MAIN'), 'Staff');

        foreach (['admin.staff.index', 'campus-dashboard.index', 'university-dashboard.index', 'monthly-reports.reviews.index', 'campus-reports.index'] as $route) {
            $this->actingAs($staff)->get(route($route))->assertForbidden();
        }
    }

    public function test_campus_librarian_staff_directory_and_details_are_campus_scoped(): void
    {
        $main = $this->campus('MAIN');
        $other = $this->campus('OTHER');
        $librarian = $this->staff($main, 'Campus Librarian', 'Main Librarian');
        $visible = $this->staff($main, 'Staff', 'Visible Main Staff');
        $hidden = $this->staff($other, 'Staff', 'Hidden Other Staff');

        $this->actingAs($librarian)->get(route('admin.staff.index'))
            ->assertOk()->assertSee($visible->name)->assertDontSee($hidden->name);
        $this->actingAs($librarian)->get(route('admin.staff.show', $visible))->assertOk();
        $this->actingAs($librarian)->get(route('admin.staff.show', $hidden))->assertForbidden();
    }

    public function test_stored_reviewer_relationship_does_not_bypass_review_permission(): void
    {
        $campus = $this->campus('MAIN');
        $owner = $this->staff($campus, 'Staff', 'Report Owner');
        $unauthorizedReviewer = $this->staff($campus, 'Staff', 'Staff Reviewer');
        $report = MonthlyReport::create([
            'report_code' => 'MRP-2026-9001',
            'user_id' => $owner->id,
            'reviewer_id' => $unauthorizedReviewer->id,
            'submitted_by' => $owner->id,
            'reporting_month' => 8,
            'reporting_year' => 2026,
            'status' => MonthlyReport::STATUS_PENDING_REVIEW,
            'submitted_at' => now(),
            'submitted_snapshot' => ['period' => [], 'staff' => [], 'performance' => [], 'narrative' => []],
        ]);

        $this->actingAs($unauthorizedReviewer)->get(route('monthly-reports.reviews.index'))->assertForbidden();
        $this->actingAs($unauthorizedReviewer)->get(route('monthly-reports.reviews.show', $report))->assertForbidden();
        $this->actingAs($unauthorizedReviewer)->post(route('monthly-reports.approve', $report))->assertForbidden();
        $this->assertSame(MonthlyReport::STATUS_PENDING_REVIEW, $report->fresh()->status);
    }

    public function test_deactivated_authenticated_session_is_rejected_from_protected_routes(): void
    {
        $staff = $this->staff($this->campus('MAIN'), 'Staff');
        $staff->update(['account_status' => 'inactive']);

        $this->actingAs($staff)->get(route('dashboard'))->assertForbidden();
        $this->actingAs($staff)->get(route('my-work.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('projects.index'))->assertForbidden();
    }

    public function test_inactive_campus_profile_cannot_access_campus_dashboard(): void
    {
        $librarian = $this->staff($this->campus('MAIN'), 'Campus Librarian');
        $librarian->staffProfile->update(['status' => 'inactive']);

        $this->actingAs($librarian)->get(route('campus-dashboard.index'))->assertForbidden();
        $this->actingAs($librarian)->get(route('admin.staff.index'))->assertForbidden();
    }

    public function test_librarian_at_inactive_campus_cannot_access_campus_dashboard(): void
    {
        $campus = $this->campus('MAIN');
        $librarian = $this->staff($campus, 'Campus Librarian');
        $campus->update(['is_active' => false]);

        $this->actingAs($librarian)->get(route('campus-dashboard.index'))->assertForbidden();
    }

    private function campus(string $code): Campus
    {
        return Campus::create(['code' => $code, 'name' => $code.' Campus', 'is_active' => true]);
    }

    private function staff(Campus $campus, string $role, ?string $name = null): User
    {
        $user = User::factory()->create([
            'name' => $name ?? $role.' '.++$this->sequence,
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
        $user->assignRole($role);
        StaffProfile::create([
            'user_id' => $user->id,
            'staff_number' => 'D20-'.++$this->sequence,
            'campus_id' => $campus->id,
            'status' => 'active',
        ]);

        return $user;
    }
}

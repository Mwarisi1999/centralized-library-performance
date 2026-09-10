<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\MonthlyReport;
use App\Models\Position;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\WorkflowNotificationService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Day20EnhancementsTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->travelTo('2026-09-09 10:00:00');
    }

    public function test_university_librarian_can_open_and_approve_pending_report_as_audited_override(): void
    {
        $campus = $this->campus('MAIN');
        $owner = $this->staff($campus, 'Staff', 'Report Owner');
        $reviewer = $this->staff($campus, 'Campus Librarian', 'Direct Reviewer');
        $universityLibrarian = $this->staff($campus, 'University Librarian', 'University Reviewer');
        $report = $this->report($owner, $reviewer, 'MRP-2026-1001');

        $this->actingAs($universityLibrarian)->get(route('monthly-reports.reviews.show', $report))
            ->assertOk()->assertSee('University Librarian override')->assertSee('Direct Reviewer');
        $this->actingAs($universityLibrarian)->post(route('monthly-reports.approve', $report), ['approval_remark' => 'Institutional review complete.'])
            ->assertRedirect(route('monthly-reports.reviews.index'));

        $this->assertSame(MonthlyReport::STATUS_APPROVED, $report->fresh()->status);
        $this->assertSame($reviewer->id, $report->fresh()->reviewer_id);
        $activity = $report->activities()->latest()->firstOrFail();
        $this->assertSame('report_override_approved', $activity->event);
        $this->assertTrue($activity->metadata['university_librarian_override']);
        $this->assertSame($reviewer->id, $activity->metadata['original_reviewer_id']);
    }

    public function test_university_librarian_can_return_report_and_owner_is_notified(): void
    {
        $campus = $this->campus('MAIN');
        $owner = $this->staff($campus, 'Staff');
        $reviewer = $this->staff($campus, 'Campus Librarian');
        $universityLibrarian = $this->staff($campus, 'University Librarian');
        $report = $this->report($owner, $reviewer, 'MRP-2026-1002');

        $this->actingAs($universityLibrarian)->post(route('monthly-reports.return', $report), ['correction_reason' => 'Clarify the recorded outcome.'])->assertRedirect();

        $this->assertSame(MonthlyReport::STATUS_RETURNED_FOR_CORRECTION, $report->fresh()->status);
        $this->assertSame('report_override_returned', $report->activities()->latest()->value('event'));
        $this->assertSame('monthly_report_returned', data_get($owner->notifications()->firstOrFail()->data, 'event'));
    }

    public function test_direct_supervisor_authority_remains_and_unrelated_staff_cannot_review(): void
    {
        $campus = $this->campus('MAIN');
        $owner = $this->staff($campus, 'Staff');
        $reviewer = $this->staff($campus, 'Campus Librarian');
        $other = $this->staff($campus, 'Staff');
        $report = $this->report($owner, $reviewer, 'MRP-2026-1003');

        $this->actingAs($reviewer)->get(route('monthly-reports.reviews.show', $report))->assertOk();
        $this->actingAs($other)->get(route('monthly-reports.reviews.show', $report))->assertForbidden();
        $this->actingAs($other)->post(route('monthly-reports.approve', $report))->assertForbidden();
        $this->actingAs($reviewer)->post(route('monthly-reports.approve', $report))->assertRedirect();
        $this->assertSame('report_approved', $report->activities()->latest()->value('event'));
    }

    public function test_campus_librarian_has_read_only_same_campus_report_oversight_but_no_cross_campus_access(): void
    {
        $main = $this->campus('MAIN');
        $otherCampus = $this->campus('OTHER');
        $librarian = $this->staff($main, 'Campus Librarian');
        $owner = $this->staff($main, 'Staff');
        $otherOwner = $this->staff($otherCampus, 'Staff');
        $actualReviewer = $this->staff($main, 'Campus Librarian');
        $sameCampus = $this->report($owner, $actualReviewer, 'MRP-2026-1004');
        $crossCampus = $this->report($otherOwner, $actualReviewer, 'MRP-2026-1005');

        $this->actingAs($librarian)->get(route('monthly-reports.reviews.show', $sameCampus))->assertOk()->assertSee('Read-only oversight');
        $this->actingAs($librarian)->post(route('monthly-reports.approve', $sameCampus))->assertForbidden();
        $this->actingAs($librarian)->get(route('monthly-reports.reviews.show', $crossCampus))->assertForbidden();
    }

    public function test_performance_analytics_enforce_campus_scope_and_university_access(): void
    {
        $main = $this->campus('MAIN');
        $other = $this->campus('OTHER');
        $campusLibrarian = $this->staff($main, 'Campus Librarian');
        $universityLibrarian = $this->staff($main, 'University Librarian');
        $mainStaff = $this->staff($main, 'Staff', 'Main Employee');
        $otherStaff = $this->staff($other, 'Staff', 'Other Employee');

        $this->actingAs($campusLibrarian)->get(route('performance.staff.show', ['staff' => $mainStaff, 'month' => 9, 'year' => 2026]))->assertOk()->assertSee('Main Employee');
        $this->actingAs($campusLibrarian)->get(route('performance.staff.show', $otherStaff))->assertForbidden();
        $this->actingAs($universityLibrarian)->get(route('performance.staff.show', ['staff' => $otherStaff, 'month' => 9, 'year' => 2026]))->assertOk()->assertSee('Other Employee');
        $this->actingAs($mainStaff)->get(route('performance.staff.show', $mainStaff))->assertForbidden();
    }

    public function test_notifications_are_private_readable_and_deduplicated(): void
    {
        $campus = $this->campus('MAIN');
        $recipient = $this->staff($campus, 'Staff');
        $other = $this->staff($campus, 'Staff');
        $service = app(WorkflowNotificationService::class);
        $service->send($recipient, 'test', 'Private notice', 'Only for recipient.', route('dashboard'), 'same-key');
        $service->send($recipient, 'test', 'Private notice', 'Only for recipient.', route('dashboard'), 'same-key');
        $notification = $recipient->notifications()->firstOrFail();

        $this->assertSame(1, $recipient->notifications()->count());
        $this->assertSame(0, $other->notifications()->count());
        $this->actingAs($other)->post(route('notifications.read', $notification))->assertForbidden();
        $this->actingAs($recipient)->post(route('notifications.read', $notification))->assertRedirect(route('dashboard'));
        $this->assertNotNull($notification->fresh()->read_at);

        $service->send($recipient, 'test_two', 'Second notice', 'Mark all coverage.', null, 'second-key');
        $this->assertSame(1, $recipient->unreadNotifications()->count());
        $this->actingAs($recipient)->post(route('notifications.read-all'))->assertRedirect();
        $this->assertSame(0, $recipient->unreadNotifications()->count());
    }

    public function test_only_administrator_can_access_organization_and_administration(): void
    {
        $campus = $this->campus('MAIN');
        $admin = $this->staff($campus, 'Administrator');
        $staff = $this->staff($campus, 'Staff');

        $this->actingAs($admin)->get(route('admin.organization.index'))->assertOk()->assertSee('Organization Setup');
        $this->actingAs($admin)->get(route('admin.administration.index'))->assertOk()->assertSee('Roles and Permissions')->assertSee('System Information');
        $this->actingAs($staff)->get(route('admin.organization.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.administration.index'))->assertForbidden();
    }

    public function test_administrator_can_create_update_and_deactivate_master_data_without_deleting_it(): void
    {
        $admin = $this->staff($this->campus('MAIN'), 'Administrator');

        $this->actingAs($admin)->post(route('admin.organization.store', 'positions'), [
            'name' => 'Repository Coordinator', 'code' => 'RC', 'description' => 'Coordinates institutional repositories.', 'is_active' => 1,
        ])->assertRedirect(route('admin.organization.index', 'positions'));
        $position = Position::where('code', 'RC')->firstOrFail();

        $this->actingAs($admin)->patch(route('admin.organization.update', ['positions', $position->id]), [
            'name' => 'Senior Repository Coordinator', 'code' => 'RC', 'description' => 'Updated description.', 'is_active' => 1,
        ])->assertRedirect();
        $this->actingAs($admin)->patch(route('admin.organization.toggle', ['positions', $position->id]))->assertRedirect();

        $this->assertDatabaseHas('positions', ['id' => $position->id, 'name' => 'Senior Repository Coordinator', 'is_active' => false, 'deleted_at' => null]);
    }

    public function test_profile_only_updates_safe_self_service_fields(): void
    {
        $campus = $this->campus('MAIN');
        $other = $this->campus('OTHER');
        $staff = $this->staff($campus, 'Staff');

        $this->actingAs($staff)->patch(route('profile.update'), [
            'name' => 'Updated Name', 'email' => 'updated@example.test', 'phone' => '+256700000000',
            'campus_id' => $other->id, 'account_status' => 'inactive', 'status' => 'inactive', 'role' => 'Administrator',
        ])->assertRedirect();

        $this->assertSame('Updated Name', $staff->fresh()->name);
        $this->assertSame('active', $staff->fresh()->account_status);
        $this->assertSame($campus->id, $staff->staffProfile->fresh()->campus_id);
        $this->assertTrue($staff->fresh()->hasRole('Staff'));
    }

    private function report(User $owner, User $reviewer, string $code): MonthlyReport
    {
        return MonthlyReport::create([
            'report_code' => $code, 'user_id' => $owner->id, 'reviewer_id' => $reviewer->id,
            'submitted_by' => $owner->id, 'reporting_month' => 9, 'reporting_year' => 2026,
            'status' => MonthlyReport::STATUS_PENDING_REVIEW, 'submitted_at' => now(),
            'submitted_snapshot' => ['period' => ['label' => 'September 2026'], 'staff' => ['name' => $owner->name], 'performance' => [], 'narrative' => []],
        ]);
    }

    private function campus(string $code): Campus
    {
        return Campus::create(['code' => $code, 'name' => $code.' Campus', 'is_active' => true]);
    }

    private function staff(Campus $campus, string $role, ?string $name = null): User
    {
        $user = User::factory()->create(['name' => $name ?? $role.' '.++$this->sequence, 'account_status' => 'active', 'email_verified_at' => now()]);
        $user->assignRole($role);
        StaffProfile::create(['user_id' => $user->id, 'staff_number' => 'ENH-'.++$this->sequence, 'campus_id' => $campus->id, 'status' => 'active']);

        return $user;
    }
}

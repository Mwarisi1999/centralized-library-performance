<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Library;
use App\Models\Position;
use App\Models\PositionJobDetail;
use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\PositionJobDetailSeeder;
use Database\Seeders\PositionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobDescriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolePermissionSeeder::class,
            PositionSeeder::class,
            PositionJobDetailSeeder::class,
        ]);
    }

    public function test_guest_cannot_open_a_job_description(): void
    {
        $this->get(route('job-description.show'))->assertRedirect(route('login'));
    }

    public function test_user_sees_the_job_description_for_their_assigned_position(): void
    {
        $user = $this->staffWithPosition('LIB');

        $this->actingAs($user)->get(route('job-description.show'))
            ->assertOk()
            ->assertSee('My Job Description')
            ->assertSee('Librarian')
            ->assertSee('Job purpose')
            ->assertSee('Duties and responsibilities')
            ->assertSee('Coordinate and maintain content for web and mobile-enabled interactive services');
    }

    public function test_available_job_description_is_visible_from_sidebar_and_dashboard(): void
    {
        $user = $this->staffWithPosition('LIB-AST');

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('My Job Description')
            ->assertSee('Your current job description')
            ->assertSee('Library Assistant');
    }

    public function test_user_without_a_documented_position_gets_a_clear_empty_state(): void
    {
        $user = $this->staffWithPosition('INTERN');

        $this->actingAs($user)->get(route('job-description.show'))
            ->assertOk()
            ->assertSee('No job description is currently available')
            ->assertSee('Intern');

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('My Job Description');
    }

    public function test_selected_job_duty_prefills_the_daily_activity_description(): void
    {
        $user = $this->staffWithPosition('LIB');
        $duty = $user->staffProfile->position->jobDetail->duties[0];

        $this->actingAs($user)->get(route('work-entries.create', ['duty' => 0]))
            ->assertOk()
            ->assertSee('Started from your current job description')
            ->assertSee($duty);
    }

    public function test_administrator_can_add_a_position_with_a_job_description_that_reaches_the_holders_dashboard(): void
    {
        $this->actingAs($this->administrator())->post(route('admin.organization.store', 'positions'), [
            'name' => 'Digital Repository Officer',
            'code' => 'DRO',
            'sort_order' => 40,
            'is_active' => 1,
            'salary_scale' => 'M6',
            'reports_to' => 'Deputy Librarian',
            'responsible_for' => 'Repository Assistants',
            'job_purpose' => 'Manage the institutional repository and its digital collections.',
            'duties' => "1. Curate repository submissions\n- Maintain metadata quality\n\n• Train staff on self-archiving",
        ])->assertRedirect(route('admin.organization.index', 'positions'));

        $position = Position::where('code', 'DRO')->firstOrFail();
        $this->assertSame(40, $position->sort_order);
        $this->assertSame(
            ['Curate repository submissions', 'Maintain metadata quality', 'Train staff on self-archiving'],
            $position->jobDetail->duties,
        );

        $holder = $this->staffWithPosition('DRO');

        $this->actingAs($holder)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Your current job description')
            ->assertSee('Digital Repository Officer');

        $this->actingAs($holder)->get(route('job-description.show'))
            ->assertOk()
            ->assertSee('Manage the institutional repository and its digital collections.')
            ->assertSee('Maintain metadata quality')
            ->assertSee('Salary scale M6');
    }

    public function test_job_description_requires_both_purpose_and_duties(): void
    {
        $this->actingAs($this->administrator())->post(route('admin.organization.store', 'positions'), [
            'name' => 'Half Documented Role',
            'is_active' => 1,
            'job_purpose' => 'A purpose without any duties.',
            'duties' => '',
        ])->assertSessionHasErrors('duties');

        $this->assertDatabaseMissing('positions', ['name' => 'Half Documented Role']);
    }

    public function test_updating_a_job_description_notifies_the_active_staff_holding_that_position(): void
    {
        $holder = $this->staffWithPosition('LIB-AST');
        $inactiveHolder = $this->staffWithPosition('LIB-AST');
        $inactiveHolder->update(['account_status' => 'suspended']);
        $otherStaff = $this->staffWithPosition('LIB');
        $position = Position::where('code', 'LIB-AST')->firstOrFail();

        $this->actingAs($this->administrator())->patch(route('admin.organization.update', ['positions', $position->id]), [
            'name' => $position->name,
            'code' => $position->code,
            'is_active' => 1,
            'job_purpose' => 'Revised purpose for library assistants.',
            'duties' => "Shelve returned items\nAssist readers at the help desk",
        ])->assertRedirect();

        $this->assertSame('Revised purpose for library assistants.', $position->fresh()->jobDetail->job_purpose);
        $this->assertSame('job_description_updated', data_get($holder->notifications()->first()?->data, 'event'));
        $this->assertSame(0, $inactiveHolder->notifications()->count());
        $this->assertSame(0, $otherStaff->notifications()->count());

        $this->actingAs($holder)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Your job description was updated');
    }

    public function test_saving_an_unchanged_job_description_does_not_notify_again(): void
    {
        $holder = $this->staffWithPosition('LIB-AST');
        $position = Position::where('code', 'LIB-AST')->firstOrFail();
        $detail = $position->jobDetail;

        $this->actingAs($this->administrator())->patch(route('admin.organization.update', ['positions', $position->id]), [
            'name' => $position->name,
            'code' => $position->code,
            'is_active' => 1,
            'salary_scale' => $detail->salary_scale,
            'reports_to' => $detail->reports_to,
            'responsible_for' => $detail->responsible_for,
            'job_purpose' => $detail->job_purpose,
            'duties' => implode("\n", $detail->duties),
        ])->assertRedirect();

        $this->assertSame(0, $holder->notifications()->count());
    }

    public function test_position_update_without_job_description_fields_keeps_the_existing_job_description(): void
    {
        $position = Position::where('code', 'LIB')->firstOrFail();

        $this->actingAs($this->administrator())->patch(route('admin.organization.update', ['positions', $position->id]), [
            'name' => $position->name,
            'code' => $position->code,
            'is_active' => 1,
        ])->assertRedirect();

        $this->assertNotNull($position->fresh()->jobDetail);
    }

    public function test_clearing_purpose_and_duties_removes_the_job_description_from_the_dashboard(): void
    {
        $holder = $this->staffWithPosition('LIB-AST');
        $position = Position::where('code', 'LIB-AST')->firstOrFail();

        $this->actingAs($this->administrator())->patch(route('admin.organization.update', ['positions', $position->id]), [
            'name' => $position->name,
            'code' => $position->code,
            'is_active' => 1,
            'job_purpose' => '',
            'duties' => '',
        ])->assertRedirect();

        $this->assertNull($position->fresh()->jobDetail);
        $this->actingAs($holder->fresh())->get(route('dashboard'))->assertOk()->assertDontSee('Your current job description');
    }

    public function test_assigning_a_documented_position_to_staff_notifies_them_about_their_job_description(): void
    {
        $campus = Campus::create(['code' => 'JD-MAIN', 'name' => 'JD Main Campus', 'is_active' => true]);
        $library = Library::create(['campus_id' => $campus->id, 'name' => 'JD Main Library', 'is_active' => true]);
        $member = $this->staffWithPosition('INTERN');
        $member->staffProfile->update(['campus_id' => $campus->id, 'library_id' => $library->id]);
        $position = Position::where('code', 'LIB')->firstOrFail();

        $payload = [
            'name' => $member->name,
            'email' => $member->email,
            'role' => 'Staff',
            'campus_id' => $campus->id,
            'library_id' => $library->id,
            'position_id' => $position->id,
            'account_status' => 'active',
        ];

        $this->actingAs($this->administrator())->put(route('admin.staff.update', $member), $payload)->assertRedirect();
        // Re-saving the same position must not repeat the alert.
        $this->actingAs($this->administrator())->put(route('admin.staff.update', $member), $payload)->assertRedirect();

        $this->assertSame(1, $member->notifications()->count());
        $this->assertSame('job_description_assigned', data_get($member->notifications()->first()->data, 'event'));

        $this->actingAs($member->fresh())->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Your job description is available')
            ->assertSee('Your current job description')
            ->assertSee('Librarian');
    }

    public function test_reseeding_keeps_job_descriptions_written_by_administrators(): void
    {
        $position = Position::create(['name' => 'Archivist', 'code' => 'ARCH', 'is_active' => true]);
        PositionJobDetail::create([
            'position_id' => $position->id,
            'job_purpose' => 'Preserve university archives.',
            'duties' => ['Appraise records'],
        ]);

        $this->seed(PositionJobDetailSeeder::class);

        $this->assertNotNull($position->fresh()->jobDetail);
    }

    private function administrator(): User
    {
        $administrator = User::factory()->create(['account_status' => 'active']);
        $administrator->assignRole('Administrator');

        return $administrator;
    }

    private function staffWithPosition(string $positionCode): User
    {
        $user = User::factory()->create(['account_status' => 'active']);
        $user->assignRole('Staff');
        StaffProfile::create([
            'user_id' => $user->id,
            'staff_number' => 'JD-'.$user->id,
            'position_id' => Position::where('code', $positionCode)->firstOrFail()->id,
            'employment_type' => 'permanent',
            'status' => 'active',
        ]);

        return $user->load('staffProfile.position.jobDetail');
    }
}

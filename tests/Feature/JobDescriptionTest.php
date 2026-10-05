<?php

namespace Tests\Feature;

use App\Models\Position;
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

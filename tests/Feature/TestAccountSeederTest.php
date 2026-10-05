<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\CampusSeeder;
use Database\Seeders\LibrarySeeder;
use Database\Seeders\PositionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\TestAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TestAccountSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_active_login_for_every_role_with_valid_reporting_profiles(): void
    {
        $this->seed([
            CampusSeeder::class,
            LibrarySeeder::class,
            PositionSeeder::class,
            RolePermissionSeeder::class,
            TestAccountSeeder::class,
        ]);

        $accounts = [
            'test.admin@example.test' => 'Administrator',
            'test.university-librarian@example.test' => 'University Librarian',
            'test.campus-librarian@example.test' => 'Campus Librarian',
            'test.me-officer@example.test' => 'M&E Officer',
            'test.staff@example.test' => 'Staff',
            'test.intern@example.test' => 'Intern',
        ];

        foreach ($accounts as $email => $role) {
            $user = User::query()->where('email', $email)->firstOrFail();

            $this->assertSame('active', $user->account_status);
            $this->assertNotNull($user->email_verified_at);
            $this->assertTrue(Hash::check(TestAccountSeeder::PASSWORD, $user->password));
            $this->assertTrue($user->hasRole($role));
        }

        $campusLibrarian = User::query()->where('email', 'test.campus-librarian@example.test')->firstOrFail();
        $staff = User::query()->where('email', 'test.staff@example.test')->firstOrFail();
        $intern = User::query()->where('email', 'test.intern@example.test')->firstOrFail();

        $this->assertSame('MAIN', $campusLibrarian->staffProfile->campus->code);
        $this->assertSame('ENG-LIB', $staff->staffProfile->library->code);
        $this->assertTrue($staff->staffProfile->supervisor->is($campusLibrarian));
        $this->assertTrue($intern->staffProfile->supervisor->is($staff));

        $this->seed(TestAccountSeeder::class);

        $this->assertSame(6, User::query()->whereIn('email', array_keys($accounts))->count());
    }
}

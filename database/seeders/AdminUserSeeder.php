<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            [
                'email' => 'admin@library.busitema.ac.ug',
            ],
            [
                'name' => 'System Administrator',
                'password' => Hash::make('ChangeMe123!'),
                'email_verified_at' => now(),
                'account_status' => 'active',
                'activated_at' => now(),
            ]
        );

        $admin->syncRoles(['Administrator']);
    }
}

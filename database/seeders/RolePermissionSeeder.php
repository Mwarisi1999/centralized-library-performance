<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Staff management
            'view staff',
            'create staff',
            'edit staff',
            'deactivate staff',

            // Projects
            'view projects',
            'create projects',
            'edit projects',
            'close projects',

            // Tasks / work activities
            'view tasks',
            'create tasks',
            'edit tasks',
            'view own tasks',
            'view supervised tasks',

            // Timesheets
            'create timesheet entries',
            'edit own timesheet entries',
            'view own timesheet',
            'view supervised timesheets',
            'view all timesheets',

            // Evidence
            'upload evidence',
            'view supervised evidence',
            'view all evidence',

            // Reports
            'view own reports',
            'submit reports',
            'view supervised reports',
            'view all reports',
            'review reports',
            'approve reports',
            'return reports',
            'reopen reports',
            'view campus reports',
            'finalize campus reports',

            // Dashboards
            'view own dashboard',
            'view campus dashboard',
            'view university dashboard',
            'view monitoring dashboard',

            // Administration
            'manage campuses',
            'manage libraries',
            'manage positions',
            'manage roles and permissions',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $roles = [
            'Administrator',
            'University Librarian',
            'M&E Officer',
            'Campus Librarian',
            'Staff',
            'Intern',
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate([
                'name' => $role,
                'guard_name' => 'web',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Administrator
        |--------------------------------------------------------------------------
        |
        | Full system access.
        |
        */

        Role::findByName('Administrator')
            ->syncPermissions(Permission::all());

        /*
        |--------------------------------------------------------------------------
        | University Librarian
        |--------------------------------------------------------------------------
        */

        Role::findByName('University Librarian')->syncPermissions([
            'view staff',
            'create staff',
            'edit staff',

            'view projects',
            'create projects',
            'edit projects',
            'close projects',

            'view tasks',
            'create tasks',
            'edit tasks',
            'view supervised tasks',

            'view all timesheets',
            'view all evidence',

            'view all reports',
            'review reports',
            'approve reports',
            'return reports',
            'reopen reports',

            'view university dashboard',
        ]);

        /*
        |--------------------------------------------------------------------------
        | M&E Officer
        |--------------------------------------------------------------------------
        */

        Role::findByName('M&E Officer')->syncPermissions([
            'view staff',
            'view projects',
            'view tasks',

            'view all timesheets',
            'view all evidence',

            'view all reports',
            'review reports',

            'view monitoring dashboard',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Campus Librarian
        |--------------------------------------------------------------------------
        */

        Role::findByName('Campus Librarian')->syncPermissions([
            'view staff',

            'view projects',
            'create projects',
            'edit projects',
            'close projects',

            'view tasks',
            'create tasks',
            'edit tasks',
            'view own tasks',
            'view supervised tasks',

            'create timesheet entries',
            'edit own timesheet entries',
            'view own timesheet',
            'view supervised timesheets',

            'upload evidence',
            'view supervised evidence',

            'view own reports',
            'submit reports',
            'view supervised reports',
            'review reports',
            'approve reports',
            'return reports',
            'reopen reports',

            'view own dashboard',
            'view campus dashboard',
            'view campus reports',
            'finalize campus reports',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Staff
        |--------------------------------------------------------------------------
        */

        Role::findByName('Staff')->syncPermissions([
            'view projects',

            'view tasks',
            'create tasks',
            'edit tasks',
            'view own tasks',

            'create timesheet entries',
            'edit own timesheet entries',
            'view own timesheet',

            'upload evidence',

            'view own reports',
            'submit reports',

            'view own dashboard',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Intern
        |--------------------------------------------------------------------------
        |
        | Interns deliberately DO NOT receive "create tasks".
        |
        */

        Role::findByName('Intern')->syncPermissions([
            'view projects',

            'view tasks',
            'view own tasks',

            'create timesheet entries',
            'edit own timesheet entries',
            'view own timesheet',

            'upload evidence',

            'view own reports',
            'submit reports',

            'view own dashboard',
        ]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}

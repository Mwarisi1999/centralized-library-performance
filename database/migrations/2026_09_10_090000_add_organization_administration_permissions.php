<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = collect([
            'manage project categories',
            'view administration',
        ])->mapWithKeys(function (string $name): array {
            $permission = Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]);

            return [$name => $permission];
        });

        Role::query()
            ->where('name', 'Administrator')
            ->where('guard_name', 'web')
            ->first()
            ?->givePermissionTo($permissions->values());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', ['manage project categories', 'view administration'])
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};

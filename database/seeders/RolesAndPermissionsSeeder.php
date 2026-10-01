<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Permission as PermissionName;
use App\Enums\UserRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Create the roles and permissions and sync each role's permissions from
     * UserRole::permissions(). Safe to run repeatedly.
     */
    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);

        // Cleared by hand because model events (which normally clear it) may be muted while seeding.
        $registrar->forgetCachedPermissions();

        foreach (PermissionName::cases() as $permission) {
            Permission::findOrCreate($permission->value, 'web');
        }

        foreach (UserRole::cases() as $role) {
            Role::findOrCreate($role->value, 'web');
        }

        $registrar->forgetCachedPermissions();

        foreach (UserRole::cases() as $role) {
            Role::findByName($role->value, 'web')->syncPermissions(
                array_map(fn (PermissionName $permission): string => $permission->value, $role->permissions()),
            );
        }

        $registrar->forgetCachedPermissions();
    }
}

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
     * Create the roles and permissions. Safe to run repeatedly.
     *
     * The super-admin role needs no permissions: a Gate::before rule grants it everything.
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

        Role::findByName(UserRole::Admin->value, 'web')->syncPermissions([
            PermissionName::AccessAdminPanel->value,
        ]);

        $registrar->forgetCachedPermissions();
    }
}

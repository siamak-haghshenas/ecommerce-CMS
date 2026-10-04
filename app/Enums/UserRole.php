<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super-admin';
    case Admin = 'admin';
    case Author = 'author';
    case Customer = 'customer';

    /**
     * Roles that make a user a staff member. Roles are fixed; there is no role editor.
     *
     * @return list<self>
     */
    public static function staffRoles(): array
    {
        return [self::SuperAdmin, self::Admin, self::Author];
    }

    /**
     * @return list<string>
     */
    public static function staffRoleValues(): array
    {
        return array_map(fn (self $role): string => $role->value, self::staffRoles());
    }

    /**
     * Permissions this role is granted. The super admin is granted everything by a
     * Gate::before rule instead, so new permissions never need to be added to it.
     *
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::SuperAdmin, self::Customer => [],
            self::Admin => [
                Permission::AccessAdminPanel,
                Permission::ViewCustomers,
                Permission::UpdateCustomers,
                Permission::DeactivateCustomers,
                Permission::ViewStaff,
                Permission::ViewCategories,
                Permission::ManageCategories,
                Permission::ViewBrands,
                Permission::ManageBrands,
                Permission::ViewAttributes,
                Permission::ManageAttributes,
                Permission::ViewProducts,
                Permission::ManageProducts,
                Permission::ViewMedia,
                Permission::UploadMedia,
                Permission::ManageMedia,
                Permission::ViewPages,
                Permission::ManagePages,
            ],
            self::Author => [
                Permission::AccessAdminPanel,
                Permission::ViewMedia,
                Permission::UploadMedia,
                Permission::ViewPages,
                Permission::ManagePages,
            ],
        };
    }
}

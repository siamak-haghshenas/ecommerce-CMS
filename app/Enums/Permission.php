<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Every permission in the system. Which role gets which is defined in UserRole::permissions();
 * permissions no role lists (e.g. anonymising customers) belong to the super admin only.
 */
enum Permission: string
{
    case AccessAdminPanel = 'access-admin-panel';

    case ViewCustomers = 'customers.view';
    case UpdateCustomers = 'customers.update';
    case DeactivateCustomers = 'customers.deactivate';
    case AnonymiseCustomers = 'customers.anonymise';

    case ViewStaff = 'staff.view';
    case ManageStaff = 'staff.manage';

    case ViewActivityLog = 'activity-log.view';

    case ViewCategories = 'categories.view';
    case ManageCategories = 'categories.manage';

    case ViewBrands = 'brands.view';
    case ManageBrands = 'brands.manage';

    /** Attributes and options (variant dimensions). */
    case ViewAttributes = 'attributes.view';
    case ManageAttributes = 'attributes.manage';
}

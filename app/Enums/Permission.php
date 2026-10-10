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

    /** Blog categories (view and manage). Product categories need the categories permissions. */
    case ManageBlogCategories = 'blog_categories.manage';

    case ViewBrands = 'brands.view';
    case ManageBrands = 'brands.manage';

    /** Attributes and options (variant dimensions). */
    case ViewAttributes = 'attributes.view';
    case ManageAttributes = 'attributes.manage';

    /** Products, their images, stock and tags. */
    case ViewProducts = 'products.view';
    case ManageProducts = 'products.manage';

    /** The media library: browse; upload and edit alt text/title; move, delete, restore and manage folders. */
    case ViewMedia = 'media.view';
    case UploadMedia = 'media.upload';
    case ManageMedia = 'media.manage';

    /** Content pages (About us, Terms, ...), their revisions included. */
    case ViewPages = 'pages.view';
    case ManagePages = 'pages.manage';

    /** The header and footer menus. */
    case ViewMenus = 'menus.view';
    case ManageMenus = 'menus.manage';

    /** Blog posts, their revisions included. */
    case ViewPosts = 'posts.view';
    case ManagePosts = 'posts.manage';

    /** Promotions (and, later, coupons). */
    case ViewDiscounts = 'discounts.view';
    case ManageDiscounts = 'discounts.manage';

    /** Store settings (price display currency, low-stock default). No role lists it: super admin only. */
    case ManageSettings = 'settings.manage';
}

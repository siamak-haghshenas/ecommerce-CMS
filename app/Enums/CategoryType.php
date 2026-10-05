<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Product and blog categories share one table; a tree never mixes the two.
 */
enum CategoryType: string
{
    case Product = 'product';
    case Blog = 'blog';

    /** Needed to see this type's categories in the admin. */
    public function viewPermission(): Permission
    {
        return match ($this) {
            self::Product => Permission::ViewCategories,
            self::Blog => Permission::ManageBlogCategories,
        };
    }

    /** Needed to create, change, reorder or delete this type's categories. */
    public function managePermission(): Permission
    {
        return match ($this) {
            self::Product => Permission::ManageCategories,
            self::Blog => Permission::ManageBlogCategories,
        };
    }

    public function seoContentType(): SeoContentType
    {
        return match ($this) {
            self::Product => SeoContentType::Category,
            self::Blog => SeoContentType::BlogCategory,
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Enums;

use App\Contracts\SeoSubject;
use App\Models\Brand;
use App\Models\Category;
use Illuminate\Database\Eloquent\Model;

/**
 * Content types using the shared SEO analysis and AI suggestions.
 * Add a case when a new content type (product, page, post) gets SEO fields.
 */
enum SeoContentType: string
{
    case Category = 'category';
    case Brand = 'brand';

    /**
     * @return class-string<Model&SeoSubject>
     */
    public function modelClass(): string
    {
        return match ($this) {
            self::Category => Category::class,
            self::Brand => Brand::class,
        };
    }

    /**
     * Needed to ask AI for suggestions: the same permission as editing the content.
     */
    public function managePermission(): Permission
    {
        return match ($this) {
            self::Category => Permission::ManageCategories,
            self::Brand => Permission::ManageBrands,
        };
    }

    /**
     * What the page is, for the AI prompt.
     */
    public function pageDescription(): string
    {
        return match ($this) {
            self::Category => 'a product category page',
            self::Brand => 'a brand page listing the products of one brand',
        };
    }
}

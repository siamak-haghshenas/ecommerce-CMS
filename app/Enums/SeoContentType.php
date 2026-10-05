<?php

declare(strict_types=1);

namespace App\Enums;

use App\Contracts\SeoSubject;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use Illuminate\Database\Eloquent\Model;

/**
 * Content types using the shared SEO analysis and AI suggestions.
 * Add a case when a new content type (product, page, post) gets SEO fields.
 */
enum SeoContentType: string
{
    case Category = 'category';
    case BlogCategory = 'blog_category';
    case Brand = 'brand';
    case Product = 'product';
    case Page = 'page';

    /**
     * @return class-string<Model&SeoSubject>
     */
    public function modelClass(): string
    {
        return match ($this) {
            self::Category, self::BlogCategory => Category::class,
            self::Brand => Brand::class,
            self::Product => Product::class,
            self::Page => Page::class,
        };
    }

    /**
     * Column values that pick this type's items out of the model's table (product and blog
     * categories share one table).
     *
     * @return array<string, string>
     */
    public function constraints(): array
    {
        return match ($this) {
            self::Category => ['type' => CategoryType::Product->value],
            self::BlogCategory => ['type' => CategoryType::Blog->value],
            default => [],
        };
    }

    /**
     * Needed to ask AI for suggestions: the same permission as editing the content.
     */
    public function managePermission(): Permission
    {
        return match ($this) {
            self::Category => Permission::ManageCategories,
            self::BlogCategory => Permission::ManageBlogCategories,
            self::Brand => Permission::ManageBrands,
            self::Product => Permission::ManageProducts,
            self::Page => Permission::ManagePages,
        };
    }

    /**
     * What the page is, for the AI prompt.
     */
    public function pageDescription(): string
    {
        return match ($this) {
            self::Category => 'a product category page',
            self::BlogCategory => 'a blog category page listing the articles of one topic',
            self::Brand => 'a brand page listing the products of one brand',
            self::Product => 'a product page',
            self::Page => 'an informational page of the store (e.g. about us, terms, shipping and returns)',
        };
    }

    /**
     * Whether the item's parent is sent to the AI (categories form a tree).
     */
    public function hasParent(): bool
    {
        return $this === self::Category || $this === self::BlogCategory;
    }
}

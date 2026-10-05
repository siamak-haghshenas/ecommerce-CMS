<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Storefront\Blog;

use App\Enums\CategoryType;
use App\Http\Controllers\Api\V1\Storefront\Catalog\CategoryController;

/**
 * The visible blog category tree and one blog category by slug: the same responses as product
 * categories, for the other type.
 */
class BlogCategoryController extends CategoryController
{
    protected function type(): CategoryType
    {
        return CategoryType::Blog;
    }
}

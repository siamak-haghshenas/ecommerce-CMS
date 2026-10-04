<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Storefront\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Storefront\CategoryResource;
use App\Http\Resources\V1\Storefront\CategoryTreeResource;
use App\Models\Category;
use App\Support\CategoryTree;

class CategoryController extends Controller
{
    /**
     * The visible category tree, nested (for menus and navigation).
     */
    public function index(): CategoryTreeResource
    {
        return new CategoryTreeResource(CategoryTree::current());
    }

    /**
     * One visible category by slug. Hidden categories, including those under a hidden parent, are 404.
     */
    public function show(string $slug): CategoryResource
    {
        $tree = CategoryTree::current();
        $node = $tree->findBySlug($slug);

        abort_if($node === null || ! $tree->isVisible($node->id), 404);

        return new CategoryResource(Category::query()->with('imageAssets.media')->findOrFail($node->id));
    }
}

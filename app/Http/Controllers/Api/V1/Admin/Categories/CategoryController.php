<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Categories;

use App\Actions\Categories\CreateCategoryAction;
use App\Actions\Categories\DeleteCategoryAction;
use App\Actions\Categories\UpdateCategoryAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Categories\SaveCategoryRequest;
use App\Http\Resources\V1\Admin\CategoryResource;
use App\Http\Resources\V1\Admin\CategoryTreeResource;
use App\Models\Category;
use App\Models\User;
use App\Support\CategoryTree;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

class CategoryController extends Controller
{
    /**
     * The whole tree, nested, hidden categories included.
     */
    public function index(): CategoryTreeResource
    {
        return new CategoryTreeResource(CategoryTree::current());
    }

    public function show(Category $category): CategoryResource
    {
        return new CategoryResource($category->load('media'));
    }

    public function store(SaveCategoryRequest $request, CreateCategoryAction $create, #[CurrentUser] User $actor): CategoryResource
    {
        return new CategoryResource($create->handle($actor, $request->toData()));
    }

    public function update(
        SaveCategoryRequest $request,
        Category $category,
        UpdateCategoryAction $update,
        #[CurrentUser] User $actor,
    ): CategoryResource {
        return new CategoryResource($update->handle($actor, $category, $request->toData())->load('media'));
    }

    public function destroy(Category $category, DeleteCategoryAction $delete, #[CurrentUser] User $actor): Response
    {
        $delete->handle($actor, $category);

        return response()->noContent();
    }
}

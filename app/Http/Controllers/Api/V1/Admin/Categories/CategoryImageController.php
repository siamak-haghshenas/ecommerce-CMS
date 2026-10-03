<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Categories;

use App\Actions\Categories\RemoveCategoryImageAction;
use App\Actions\Categories\UpdateCategoryImageAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Categories\UpdateCategoryImageRequest;
use App\Http\Resources\V1\Admin\CategoryResource;
use App\Models\Category;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;

class CategoryImageController extends Controller
{
    public function store(
        UpdateCategoryImageRequest $request,
        Category $category,
        UpdateCategoryImageAction $updateImage,
        #[CurrentUser] User $actor,
    ): CategoryResource {
        return new CategoryResource($updateImage->handle($actor, $category, $request->uploadedImage()));
    }

    public function destroy(Category $category, RemoveCategoryImageAction $removeImage, #[CurrentUser] User $actor): CategoryResource
    {
        return new CategoryResource($removeImage->handle($actor, $category));
    }
}

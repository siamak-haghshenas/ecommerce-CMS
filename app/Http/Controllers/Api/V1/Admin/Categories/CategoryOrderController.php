<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Categories;

use App\Actions\Categories\ReorderCategoriesAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Categories\ReorderCategoriesRequest;
use App\Http\Resources\V1\Admin\CategoryTreeResource;
use App\Models\User;
use App\Support\CategoryTree;
use Illuminate\Container\Attributes\CurrentUser;

class CategoryOrderController extends Controller
{
    /**
     * Reorder or move categories (drag and drop, move up/down, "move to") and return the updated tree.
     */
    public function __invoke(ReorderCategoriesRequest $request, ReorderCategoriesAction $reorder, #[CurrentUser] User $actor): CategoryTreeResource
    {
        $reorder->handle($actor, $request->type(), $request->parentId(), $request->ids());

        return new CategoryTreeResource(CategoryTree::current(), $request->type());
    }
}

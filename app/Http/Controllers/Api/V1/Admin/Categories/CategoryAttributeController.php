<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Categories;

use App\Actions\Catalog\SetCategoryAttributesAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Catalog\SetCategoryAttributesRequest;
use App\Http\Resources\V1\Admin\AttributeResource;
use App\Models\Category;
use App\Models\User;
use App\Support\CategoryTree;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

class CategoryAttributeController extends Controller
{
    /**
     * The category's own attributes plus those inherited from its ancestors
     * (nearest first wins; an attribute it also has itself is listed as its own).
     */
    public function show(Category $category): JsonResponse
    {
        $own = $category->attributeDefinitions()->get();
        $ownIds = $own->pluck('id')->all();

        $ancestorIds = array_map(fn ($node): int => $node->id, CategoryTree::current()->ancestors($category->id));
        $ancestors = Category::query()->with('attributeDefinitions')->findMany($ancestorIds)->keyBy('id');

        $inherited = [];

        // Nearest ancestor first, so the closest category is shown as the source.
        foreach (array_reverse($ancestorIds) as $ancestorId) {
            foreach ($ancestors[$ancestorId]->attributeDefinitions ?? [] as $attribute) {
                if (! in_array($attribute->id, $ownIds, true) && ! isset($inherited[$attribute->id])) {
                    $inherited[$attribute->id] = [
                        'attribute' => new AttributeResource($attribute),
                        'from' => ['id' => $ancestorId, 'name' => $ancestors[$ancestorId]->name],
                    ];
                }
            }
        }

        return response()->json(['data' => [
            'own' => AttributeResource::collection($own),
            'inherited' => array_values($inherited),
        ]]);
    }

    public function update(SetCategoryAttributesRequest $request, Category $category, SetCategoryAttributesAction $set, #[CurrentUser] User $actor): JsonResponse
    {
        $set->handle($actor, $category, $request->ids());

        return $this->show($category);
    }
}

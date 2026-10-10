<?php

declare(strict_types=1);

namespace App\Actions\Categories;

use App\Actions\Pricing\RefreshPricesAction;
use App\Actions\Products\RefreshProductSearchTextAction;
use App\Actions\Seo\ScoreSeoAction;
use App\Data\Categories\CategoryData;
use App\Enums\CategoryType;
use App\Enums\MediaUsageRole;
use App\Exceptions\CategoryException;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Support\CategoryTree;

final class UpdateCategoryAction
{
    public function __construct(
        private readonly ScoreSeoAction $scoreSeo,
        private readonly RefreshProductSearchTextAction $refreshSearchText,
        private readonly RefreshPricesAction $refreshPrices,
    ) {}

    /**
     * Update a category. A new parent places it last among its new siblings.
     *
     * @throws CategoryException when the new parent is the category itself or one of its descendants
     */
    public function handle(User $actor, Category $category, CategoryData $data): Category
    {
        $previousParentId = $category->parent_id;
        $previousKeyphrase = $category->focus_keyphrase;
        $isMoving = $data->parentId !== $previousParentId;

        if ($isMoving && ! CategoryTree::current()->canMoveUnder($category->id, $data->parentId)) {
            throw CategoryException::invalidParent();
        }

        $category->fill([
            'name' => $data->name,
            'slug' => $data->slug,
            'description' => $data->description,
            'meta_title' => $data->metaTitle,
            'meta_description' => $data->metaDescription,
            'focus_keyphrase' => $data->focusKeyphrase,
            'is_active' => $data->isActive,
        ]);

        $changedFields = array_keys($category->getDirty());

        if ($isMoving) {
            $category->parent_id = $data->parentId;
            $category->position = Category::nextPositionUnder($data->parentId, $category->type);
        }

        $category->save();

        if ($category->syncMediaAssets(MediaUsageRole::Image, $data->imageId === null ? [] : [$data->imageId])) {
            $changedFields[] = 'image';
            // The cached tree carries the images.
            CategoryTree::forget();
        }

        if ($changedFields === [] && ! $isMoving) {
            return $category;
        }

        // Product search includes category names.
        if (in_array('name', $changedFields, true)) {
            $this->refreshSearchText->forQuery(Product::query()->whereHas('categories', fn ($query) => $query->whereKey($category->id)));
        }
        $this->scoreSeo->handle($category, $previousKeyphrase);

        if ($changedFields !== []) {
            activity('admin')
                ->causedBy($actor)
                ->performedOn($category)
                ->event('category.updated')
                ->withProperties(['fields' => $changedFields])
                ->log('category.updated');
        }

        if ($isMoving) {
            activity('admin')
                ->causedBy($actor)
                ->performedOn($category)
                ->event('category.moved')
                ->withProperties(['from' => $previousParentId, 'to' => $category->parent_id])
                ->log('category.moved');

            // Promotions on a category include its sub-categories: the moved branch may gain or lose them.
            if ($category->type === CategoryType::Product) {
                $this->refreshPrices->handle();
            }
        }

        return $category;
    }
}

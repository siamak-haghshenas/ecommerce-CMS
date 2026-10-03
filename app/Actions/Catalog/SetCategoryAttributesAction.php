<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\Category;
use App\Models\User;

final class SetCategoryAttributesAction
{
    /**
     * Set a category's own attributes, in order. Sub-categories inherit them.
     *
     * @param  list<int>  $attributeIds
     */
    public function handle(User $actor, Category $category, array $attributeIds): void
    {
        $current = $category->attributeDefinitions()->pluck('attributes.id')->all();

        if ($current === $attributeIds) {
            return;
        }

        $category->attributeDefinitions()->sync(
            collect($attributeIds)->mapWithKeys(fn (int $id, int $position): array => [$id => ['position' => $position]])->all(),
        );

        activity('admin')
            ->causedBy($actor)
            ->performedOn($category)
            ->event('category.attributes_changed')
            ->withProperties(['attribute_ids' => $attributeIds])
            ->log('category.attributes_changed');
    }
}

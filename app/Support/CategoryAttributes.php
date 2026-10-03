<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Attribute;
use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;

/**
 * The specifications that apply to products of some categories: each category's own
 * attributes plus those of its ancestors, combined over all the categories, each once.
 */
final class CategoryAttributes
{
    /**
     * In order: per category (in the given order), ancestors first, then its own.
     *
     * @param  list<int>  $categoryIds
     * @return Collection<int, Attribute>
     */
    public static function applicable(array $categoryIds): Collection
    {
        $tree = CategoryTree::current();
        $chain = [];

        foreach ($categoryIds as $categoryId) {
            foreach ($tree->ancestors($categoryId) as $ancestor) {
                $chain[$ancestor->id] = true;
            }

            $chain[$categoryId] = true;
        }

        if ($chain === []) {
            return new Collection;
        }

        $categories = Category::query()->with('attributeDefinitions.values')->findMany(array_keys($chain))->keyBy('id');
        $attributes = [];

        foreach (array_keys($chain) as $id) {
            foreach ($categories[$id]->attributeDefinitions ?? [] as $attribute) {
                $attributes[$attribute->id] ??= $attribute;
            }
        }

        return new Collection(array_values($attributes));
    }
}

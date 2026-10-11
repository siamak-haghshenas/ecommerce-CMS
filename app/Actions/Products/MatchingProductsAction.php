<?php

declare(strict_types=1);

namespace App\Actions\Products;

use App\Data\Products\ProductFilters;
use App\Models\Product;

final class MatchingProductsAction
{
    /** The most products handed out at once ("select all matching" in pickers). */
    public const LIMIT = 500;

    /**
     * Every product matching the admin list's filters (id and name), in the list's order. Above the
     * limit none are returned, only the total, so a picker asks for narrower filters instead.
     *
     * @return array{products: list<array{id: int, name: string}>, total: int, limit: int}
     */
    public function handle(ProductFilters $filters): array
    {
        $query = Product::query()->matching($filters);
        $total = (clone $query)->count();

        $products = $total > self::LIMIT ? [] : $query
            ->when($filters->sort === 'name', fn ($query) => $query->orderBy('name'))
            ->when($filters->sort === 'updated', fn ($query) => $query->latest('updated_at'))
            ->latest('id')
            ->get(['id', 'name'])
            ->map(fn (Product $product): array => ['id' => $product->id, 'name' => $product->name])
            ->values()
            ->all();

        return ['products' => $products, 'total' => $total, 'limit' => self::LIMIT];
    }
}

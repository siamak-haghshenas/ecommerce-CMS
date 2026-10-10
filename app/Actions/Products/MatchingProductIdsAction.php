<?php

declare(strict_types=1);

namespace App\Actions\Products;

use App\Data\Products\ProductFilters;
use App\Models\Product;

final class MatchingProductIdsAction
{
    /** The most ids handed out at once ("select all matching" in pickers). */
    public const LIMIT = 500;

    /**
     * Every product matching the admin list's filters, in the list's order. Above the limit no ids
     * are returned, only the total, so a picker asks for narrower filters instead.
     *
     * @return array{ids: list<int>, total: int, limit: int}
     */
    public function handle(ProductFilters $filters): array
    {
        $query = Product::query()->matching($filters);
        $total = (clone $query)->count();

        $ids = $total > self::LIMIT ? [] : $query
            ->when($filters->sort === 'name', fn ($query) => $query->orderBy('name'))
            ->when($filters->sort === 'updated', fn ($query) => $query->latest('updated_at'))
            ->latest('id')
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();

        return ['ids' => $ids, 'total' => $total, 'limit' => self::LIMIT];
    }
}

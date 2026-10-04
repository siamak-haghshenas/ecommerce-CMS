<?php

declare(strict_types=1);

namespace App\Actions\Products;

use App\Data\Products\ProductFilters;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class SearchProductsAction
{
    /**
     * The admin product list (and the bulk editor's pages).
     *
     * @param  list<string>  $with
     * @return LengthAwarePaginator<int, Product>
     */
    public function handle(ProductFilters $filters, array $with = ['brand', 'categories', 'variants', 'media']): LengthAwarePaginator
    {
        $query = Product::query()->with($with)->matching($filters);

        match ($filters->sort) {
            'name' => $query->orderBy('name'),
            'updated' => $query->latest('updated_at'),
            default => $query->latest('id'),
        };

        return $query->paginate($filters->perPage);
    }
}

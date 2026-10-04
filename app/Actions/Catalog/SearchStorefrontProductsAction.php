<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Data\Catalog\StorefrontProductFilters;
use App\Models\Product;
use App\Support\Catalog\StorefrontProductQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class SearchStorefrontProductsAction
{
    /**
     * The public product list: visible products matching every filter, sorted and paginated.
     *
     * @return LengthAwarePaginator<int, Product>
     */
    public function handle(StorefrontProductFilters $filters): LengthAwarePaginator
    {
        $query = StorefrontProductQuery::visible();
        StorefrontProductQuery::applyScope($query, $filters);
        StorefrontProductQuery::applyRefinements($query, $filters);
        StorefrontProductQuery::withMinPrice($query);

        $query->with([
            'brand',
            'galleryAssets.media',
            'variants' => fn ($variants) => $variants->where('is_enabled', true),
        ]);

        match ($filters->sort) {
            'price_asc' => $query->orderBy('min_price')->orderByDesc('products.id'),
            'price_desc' => $query->orderByDesc('min_price')->orderByDesc('products.id'),
            'name' => $query->orderBy('products.name'),
            default => $query->orderByDesc('published_at')->orderByDesc('products.id'),
        };

        return $query->paginate($filters->perPage);
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\SaleEvents;

use App\Http\Resources\V1\Admin\SaleEventVariantResource;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class FindEventVariantsAction
{
    /**
     * The enabled variants of these products (deleted products and variants left out), product by
     * product in the order given, each product's variants in their own order: what "Add products"
     * adds to a sale event.
     *
     * @param  list<int>  $productIds
     * @return Collection<int, ProductVariant>
     */
    public function handle(array $productIds): Collection
    {
        $order = array_flip($productIds);

        return ProductVariant::query()
            ->with(SaleEventVariantResource::relations())
            ->where('is_enabled', true)
            ->whereIn('product_id', $productIds)
            ->whereHas('product', fn (Builder $product) => $product->withoutTrashed())
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->sortBy(fn (ProductVariant $variant): int => $order[$variant->product_id] ?? PHP_INT_MAX)
            ->values();
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Promotions;

use App\Data\Products\ProductFilters;
use App\Http\Resources\V1\Admin\PromotionPickerProductResource;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class FindPromotionVariantsAction
{
    /**
     * The "Add product" dialog: products matching the product list's filters, by name, with their
     * enabled variants (only the ones with something to sell when filtering on in stock).
     *
     * @return LengthAwarePaginator<int, Product>
     */
    public function handle(ProductFilters $filters): LengthAwarePaginator
    {
        return Product::query()
            ->matching($filters)
            ->orderBy('name')
            ->orderBy('id')
            ->with(PromotionPickerProductResource::relations())
            ->paginate($filters->perPage);
    }
}

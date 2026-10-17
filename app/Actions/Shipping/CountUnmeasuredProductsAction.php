<?php

declare(strict_types=1);

namespace App\Actions\Shipping;

use App\Models\Product;

final class CountUnmeasuredProductsAction
{
    /**
     * Products (not deleted) shipping can't fully measure: without a weight (weight methods use
     * the store's default weight, or hide) and missing a dimension (volume methods hide).
     *
     * @return array{missing_weight: int, missing_dimensions: int}
     */
    public function handle(): array
    {
        return [
            'missing_weight' => Product::query()->missingWeight()->count(),
            'missing_dimensions' => Product::query()->missingDimensions()->count(),
        ];
    }
}

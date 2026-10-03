<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Products;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Admin\StockMovementResource;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StockMovementController extends Controller
{
    /**
     * The stock history of all the product's variants, newest first.
     */
    public function __invoke(Product $product): AnonymousResourceCollection
    {
        return StockMovementResource::collection(
            StockMovement::query()
                ->with(['variant', 'user'])
                ->whereIn('product_variant_id', $product->variants()->withTrashed()->select('id'))
                ->latest('id')
                ->paginate(30),
        );
    }
}

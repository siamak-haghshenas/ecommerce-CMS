<?php

declare(strict_types=1);

namespace App\Actions\Promotions;

use App\Http\Resources\V1\Admin\PromotionResource;
use App\Models\Promotion;
use Illuminate\Database\Eloquent\Collection;

final readonly class ListPromotionsAction
{
    /**
     * Every promotion row, grouped by product (by name), then in variant order and by start; the
     * page filters, counts and pages them in the browser.
     *
     * @return Collection<int, Promotion>
     */
    public function handle(): Collection
    {
        return Promotion::query()
            ->select('promotions.*')
            ->join('products', 'products.id', '=', 'promotions.product_id')
            ->join('product_variants', 'product_variants.id', '=', 'promotions.product_variant_id')
            ->orderBy('products.name')
            ->orderBy('promotions.product_id')
            ->orderBy('product_variants.position')
            ->orderBy('product_variants.id')
            ->orderBy('promotions.starts_at')
            ->with(PromotionResource::relations())
            ->get();
    }
}

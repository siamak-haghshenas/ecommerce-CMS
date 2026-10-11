<?php

declare(strict_types=1);

namespace App\Actions\Pricing;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Pricing\Pricing;
use App\Support\Pricing\PromotionSet;
use App\Support\Pricing\SaleEventSet;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

final readonly class RefreshPricesAction
{
    public function __construct(private Pricing $pricing) {}

    /**
     * Store the current price of the given products' variants (null: every product). Called when
     * a product, a sale event or promotions change, and each minute for prices whose sale, event or
     * promotion starts or ends (`prices:refresh --due`).
     *
     * @param  list<int>|null  $productIds
     * @return int how many variants changed
     */
    public function handle(?array $productIds = null): int
    {
        if ($productIds === []) {
            return 0;
        }

        $events = SaleEventSet::load($productIds);
        $promotions = PromotionSet::load($productIds);
        $now = now();
        $changed = 0;

        Product::withTrashed()
            ->when($productIds !== null, fn (Builder $query) => $query->whereKey($productIds))
            ->with('variants')
            ->chunkById(200, function ($products) use ($events, $promotions, $now, &$changed): void {
                foreach ($products as $product) {
                    foreach ($product->variants as $variant) {
                        $changed += $this->store($variant, $events, $promotions, $now) ? 1 : 0;
                    }
                }
            });

        return $changed;
    }

    private function store(ProductVariant $variant, SaleEventSet $events, PromotionSet $promotions, CarbonInterface $now): bool
    {
        $price = $this->pricing->price($variant, $now, $events->forVariant($variant->id), $promotions->forVariant($variant->id));

        $variant->forceFill([
            'current_price' => $price->current,
            'discount_source' => $price->source,
            'sale_event_id' => $price->saleEventId,
            'promotion_id' => $price->promotionId,
            'max_per_order' => $price->maxPerOrder,
            'price_recalc_at' => $price->recalcAt,
        ]);

        if (! $variant->isDirty()) {
            return false;
        }

        // A price change is not an edit of the variant: no timestamp, no saving hook.
        $variant->timestamps = false;
        $variant->saveQuietly();
        $variant->timestamps = true;

        return true;
    }
}

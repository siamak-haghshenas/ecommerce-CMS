<?php

declare(strict_types=1);

namespace App\Actions\Pricing;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Support\Pricing\Pricing;
use App\Support\Pricing\PromotionSet;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

final readonly class RefreshPricesAction
{
    public function __construct(private Pricing $pricing) {}

    /**
     * Store the current price of the given products' variants (null: every product). Called when
     * a product, a promotion or what promotions target (categories, tags) changes, and each
     * minute for prices whose sale or promotion starts or ends (`prices:refresh --due`).
     *
     * @param  list<int>|null  $productIds
     * @return int how many variants changed
     */
    public function handle(?array $productIds = null): int
    {
        if ($productIds === []) {
            return 0;
        }

        $promotions = PromotionSet::load();
        $now = now();
        $changed = 0;

        Product::withTrashed()
            ->when($productIds !== null, fn (Builder $query) => $query->whereKey($productIds))
            ->with(['categories:id', 'tags:id', 'variants'])
            ->chunkById(200, function ($products) use ($promotions, $now, &$changed): void {
                foreach ($products as $product) {
                    $applicable = $promotions->forProduct(
                        $product->id,
                        $product->brand_id,
                        $product->categories->modelKeys(),
                        $product->tags->modelKeys(),
                    );

                    foreach ($product->variants as $variant) {
                        $changed += $this->store($variant, $applicable, $now) ? 1 : 0;
                    }
                }
            });

        return $changed;
    }

    /**
     * @param  list<Promotion>  $promotions
     */
    private function store(ProductVariant $variant, array $promotions, CarbonInterface $now): bool
    {
        $price = $this->pricing->price($variant, $promotions, $now);

        $variant->forceFill([
            'current_price' => $price->current,
            'discount_source' => $price->source,
            'promotion_id' => $price->promotionId,
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

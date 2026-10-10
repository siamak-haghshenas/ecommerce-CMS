<?php

declare(strict_types=1);

namespace App\Support\Pricing;

use App\Data\Pricing\VariantPrice;
use App\Enums\DiscountSource;
use App\Enums\PromotionType;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Support\Settings;
use Carbon\CarbonInterface;

/**
 * The one place prices are worked out: the lowest of the regular price, the variant's running
 * sale price and every running promotion that targets it. Discounts never stack. Promotion
 * discounts are rounded down to whole Rials, or to whole Toman when the store shows Toman.
 */
final readonly class Pricing
{
    public function __construct(private Settings $settings) {}

    /**
     * @param  list<Promotion>  $promotions  the promotions that target the variant's product
     */
    public function price(ProductVariant $variant, array $promotions, CarbonInterface $at): VariantPrice
    {
        $best = new VariantPrice($variant->price, null, null, null);
        $changes = [];

        if ($variant->sale_price !== null && $variant->sale_price < $variant->price) {
            if ($variant->isOnSale($at)) {
                $best = new VariantPrice((int) $variant->sale_price, DiscountSource::Sale, null, null);
            }

            $changes[] = $variant->sale_starts_at;
            $changes[] = $variant->sale_ends_at;
        }

        foreach ($promotions as $promotion) {
            $changes[] = $promotion->starts_at;
            $changes[] = $promotion->ends_at;

            if ($promotion->state($at) !== 'active') {
                continue;
            }

            $price = $variant->price - $this->discount($promotion, $variant->price);

            // Lower wins; on a tie the variant's own sale is kept.
            if ($price < $best->current) {
                $best = new VariantPrice($price, DiscountSource::Promotion, $promotion->id, null);
            }
        }

        $future = array_filter($changes, fn (?CarbonInterface $moment): bool => $moment !== null && $moment->gt($at));
        $next = $future === [] ? null : min($future);

        return new VariantPrice($best->current, $best->source, $best->promotionId, $next);
    }

    /**
     * Rials off one unit with a regular price of $price.
     */
    public function discount(Promotion $promotion, int $price): int
    {
        $discount = $promotion->type === PromotionType::Percent
            ? intdiv($price * $promotion->value, 100)
            : $promotion->value;

        if ($promotion->type === PromotionType::Percent && $promotion->max_discount !== null) {
            $discount = min($discount, $promotion->max_discount);
        }

        $unit = $this->settings->priceDisplayCurrency()->rialsPerUnit();

        return max(0, min($price, intdiv($discount, $unit) * $unit));
    }
}

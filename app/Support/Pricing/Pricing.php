<?php

declare(strict_types=1);

namespace App\Support\Pricing;

use App\Data\Pricing\VariantPrice;
use App\Enums\DiscountSource;
use App\Enums\PromotionType;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\SaleEventItem;
use App\Support\Settings;
use Carbon\CarbonInterface;

/**
 * The one place prices are worked out: the lowest of the regular price, the variant's running
 * sale price, every running promotion that targets it and the sale event chosen for it (of the
 * events running for the variant, the highest-priority one). Discounts never stack. Promotion and
 * event discounts are rounded down to whole Rials, or to whole Toman when the store shows Toman.
 */
final readonly class Pricing
{
    public function __construct(private Settings $settings) {}

    /**
     * @param  list<Promotion>  $promotions  the promotions that target the variant's product
     * @param  list<SaleEventItem>  $eventItems  the variant's items in events, highest priority first (with `saleEvent`)
     */
    public function price(ProductVariant $variant, array $promotions, CarbonInterface $at, array $eventItems = []): VariantPrice
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

        $chosen = null;

        foreach ($eventItems as $item) {
            $changes[] = $item->startsAt();
            $changes[] = $item->endsAt();

            // Priority picks one event: the first running one whose discount is possible.
            if ($chosen === null && $item->runsAt($at)) {
                $discount = $this->eventDiscount($item, $variant->price);
                $chosen = $discount > 0 && $discount < $variant->price ? $item : null;

                if ($chosen !== null && $variant->price - $discount < $best->current) {
                    $best = new VariantPrice($variant->price - $discount, DiscountSource::SaleEvent, null, null, $item->sale_event_id);
                }
            }
        }

        $future = array_filter($changes, fn (?CarbonInterface $moment): bool => $moment !== null && $moment->gt($at));
        $next = $future === [] ? null : min($future);

        return new VariantPrice($best->current, $best->source, $best->promotionId, $next, $best->saleEventId);
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

        return $this->roundDown($discount, $price);
    }

    /**
     * Rials off one unit of a variant in a sale event, with a regular price of $price.
     */
    public function eventDiscount(SaleEventItem $item, int $price): int
    {
        $discount = $item->discount_type === PromotionType::Percent
            ? intdiv($price * $item->discount_value, 100)
            : $item->discount_value;

        return $this->roundDown($discount, $price);
    }

    /**
     * Whole Rials, or whole Toman when the store shows Toman; never more than the price.
     */
    private function roundDown(int $discount, int $price): int
    {
        $unit = $this->settings->priceDisplayCurrency()->rialsPerUnit();

        return max(0, min($price, intdiv($discount, $unit) * $unit));
    }
}

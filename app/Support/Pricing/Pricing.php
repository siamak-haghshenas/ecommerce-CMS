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
 * The one place prices are worked out: the lowest of the variant's running sale price, the sale
 * event chosen for it (of the events running for the variant, the highest-priority one) and its
 * running promotion row, never above the regular price. Discounts never stack. Event and promotion
 * discounts are rounded down to whole Rials, or to whole Toman when the store shows Toman. The order
 * limit of whatever gives the price comes with it.
 */
final readonly class Pricing
{
    public function __construct(private Settings $settings) {}

    /**
     * @param  list<SaleEventItem>  $eventItems  the variant's items in events, highest priority first (with `saleEvent`)
     * @param  list<Promotion>  $promotions  the variant's promotion rows that haven't ended
     */
    public function price(ProductVariant $variant, CarbonInterface $at, array $eventItems = [], array $promotions = []): VariantPrice
    {
        $best = new VariantPrice($variant->price, null, null);
        $changes = [];

        if ($variant->sale_price !== null && $variant->sale_price < $variant->price) {
            if ($variant->isOnSale($at)) {
                $best = new VariantPrice((int) $variant->sale_price, DiscountSource::Sale, null);
            }

            $changes[] = $variant->sale_starts_at;
            $changes[] = $variant->sale_ends_at;
        }

        $chosen = null;

        foreach ($eventItems as $item) {
            $changes[] = $item->startsAt();
            $changes[] = $item->endsAt();

            // Priority picks one event: the first running one whose discount is possible.
            if ($chosen === null && $item->runsAt($at)) {
                $discount = $this->eventDiscount($item, $variant->price);
                $chosen = $discount > 0 && $discount < $variant->price ? $item : null;

                // Lower wins; on a tie the variant's own sale is kept.
                if ($chosen !== null && $variant->price - $discount < $best->current) {
                    $best = new VariantPrice($variant->price - $discount, DiscountSource::SaleEvent, null, $item->sale_event_id, $item->max_per_order);
                }
            }
        }

        foreach ($promotions as $promotion) {
            $changes[] = $promotion->starts_at;
            $changes[] = $promotion->ends_at;

            if (! $promotion->runsAt($at)) {
                continue;
            }

            $discount = $this->promotionDiscount($promotion, $variant->price);

            // Lower wins; on a tie the sale or event is kept.
            if ($discount > 0 && $discount < $variant->price && $variant->price - $discount < $best->current) {
                $best = new VariantPrice($variant->price - $discount, DiscountSource::Promotion, null, null, $promotion->max_per_order, $promotion->id);
            }
        }

        $future = array_filter($changes, fn (?CarbonInterface $moment): bool => $moment !== null && $moment->gt($at));
        $next = $future === [] ? null : min($future);

        return new VariantPrice($best->current, $best->source, $next, $best->saleEventId, $best->maxPerOrder, $best->promotionId);
    }

    /**
     * Rials off one unit of a variant in a sale event, with a regular price of $price.
     */
    public function eventDiscount(SaleEventItem $item, int $price): int
    {
        return $this->discount($item->discount_type, $item->discount_value, $price);
    }

    /**
     * Rials off one unit of a variant in a promotion row, with a regular price of $price.
     */
    public function promotionDiscount(Promotion $promotion, int $price): int
    {
        return $this->discount($promotion->discount_type, $promotion->discount_value, $price);
    }

    private function discount(PromotionType $type, int $value, int $price): int
    {
        return $this->roundDown($type === PromotionType::Percent ? intdiv($price * $value, 100) : $value, $price);
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

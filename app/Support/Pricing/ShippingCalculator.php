<?php

declare(strict_types=1);

namespace App\Support\Pricing;

use App\Data\Coupons\CouponItem;
use App\Data\Coupons\CouponQuote;
use App\Data\Shipping\ShippingBasket;
use App\Data\Shipping\ShippingBasketLine;
use App\Data\Shipping\ShippingOption;
use App\Data\Shipping\ShippingRateData;
use App\Data\Shipping\ShippingTerms;
use App\Enums\ShippingFreeReason;
use App\Enums\ShippingMethodType;
use App\Enums\ShippingUnavailableReason;
use App\Models\ProductVariant;
use App\Support\Settings;

/**
 * Works shipping out for a cart, for the admin's test box, the storefront's quote and later
 * checkout.
 *
 * - Weight methods: the order's weight (each product's weight × quantity, the store's default
 *   weight for products without one, plus the method's packaging weight) picks a range.
 * - Volume methods: the order's volume (length × width × height of each product × quantity)
 *   picks a range; a product missing a dimension hides the method.
 * - Couriers: the address's city must be one of the method's; its own fee, else the default.
 *
 * A range includes its start and excludes its end. The minimum order and "free over" amounts are
 * compared with the items' total after the coupon discount; a free-shipping coupon also makes
 * the fee 0. When the carrier collects the fee (`fee_on_delivery`) it isn't paid online.
 */
final readonly class ShippingCalculator
{
    public function __construct(private Settings $settings) {}

    /**
     * Measure a cart. Variants that don't exist (or whose product is deleted) are left out.
     *
     * @param  list<CouponItem>  $items
     * @param  CouponQuote|null  $coupon  a coupon already worked out for the same items; one with a problem takes nothing off
     */
    public function basket(array $items, ?CouponQuote $coupon = null): ShippingBasket
    {
        $variants = ProductVariant::query()
            ->with(['product' => fn ($query) => $query->select(['id', 'weight_grams', 'length_mm', 'width_mm', 'height_mm'])])
            ->whereKey(array_map(fn (CouponItem $item): int => $item->variantId, $items))
            ->get()
            ->keyBy('id');

        $defaultWeight = $this->settings->defaultWeightGrams();
        $lines = [];

        foreach ($items as $item) {
            $variant = $variants->get($item->variantId);
            $product = $variant?->product;

            if ($variant === null || $product === null) {
                continue;
            }

            $dimensions = [$product->length_mm, $product->width_mm, $product->height_mm];
            $hasDimensions = ! in_array(null, $dimensions, true) && ! in_array(0, $dimensions, true);

            $lines[] = new ShippingBasketLine(
                variantId: $variant->id,
                quantity: $item->quantity,
                unitPrice: $variant->currentPrice(),
                unitWeightGrams: $product->weight_grams ?? $defaultWeight,
                usesDefaultWeight: $product->weight_grams === null && $defaultWeight !== null,
                // mm³ → cm³, rounded up.
                unitVolumeCm3: $hasDimensions ? (int) ceil(array_product($dimensions) / 1000) : null,
            );
        }

        $sum = function (callable $value) use ($lines): ?int {
            $total = 0;

            foreach ($lines as $line) {
                $unit = $value($line);

                if ($unit === null) {
                    return null;
                }

                $total += $unit * $line->quantity;
            }

            return $total;
        };

        $valid = $coupon !== null && $coupon->problem === null;

        return new ShippingBasket(
            lines: $lines,
            subtotal: (int) $sum(fn (ShippingBasketLine $line): int => $line->unitPrice),
            couponDiscount: $valid ? $coupon->discount : 0,
            freeShippingCoupon: $valid && $coupon->freeShipping,
            weightGrams: $sum(fn (ShippingBasketLine $line): ?int => $line->unitWeightGrams),
            volumeCm3: $sum(fn (ShippingBasketLine $line): ?int => $line->unitVolumeCm3),
        );
    }

    /**
     * @param  int|null  $cityId  the address's city (couriers need one)
     */
    public function quote(ShippingTerms $terms, ShippingBasket $basket, ?int $cityId): ShippingOption
    {
        if (! $terms->isActive) {
            return new ShippingOption(ShippingUnavailableReason::Inactive);
        }

        if ($terms->type === ShippingMethodType::Courier && ($cityId === null || ! array_key_exists($cityId, $terms->cityFees))) {
            return new ShippingOption(ShippingUnavailableReason::CityNotServed);
        }

        $total = $basket->total();

        if ($terms->minOrderAmount !== null && $total < $terms->minOrderAmount) {
            return new ShippingOption(ShippingUnavailableReason::BelowMinOrder);
        }

        $measure = null;
        $rate = null;

        if ($terms->type === ShippingMethodType::Courier) {
            $originalFee = (int) ($terms->cityFees[(int) $cityId] ?? $terms->defaultFee);
        } else {
            $measure = $terms->type === ShippingMethodType::Weight ? $basket->weightGrams : $basket->volumeCm3;

            if ($measure === null) {
                return new ShippingOption($terms->type === ShippingMethodType::Weight
                    ? ShippingUnavailableReason::MissingWeight
                    : ShippingUnavailableReason::MissingDimensions);
            }

            if ($terms->type === ShippingMethodType::Weight) {
                $measure += (int) $terms->packagingWeightGrams;
            }

            $rate = array_values(array_filter($terms->rates, fn (ShippingRateData $rate): bool => $rate->covers($measure)))[0] ?? null;

            if ($rate === null) {
                return new ShippingOption(ShippingUnavailableReason::OutOfRange, $measure);
            }

            $originalFee = $rate->fee;
        }

        $freeReason = match (true) {
            $terms->freeOverAmount !== null && $total >= $terms->freeOverAmount => ShippingFreeReason::FreeOver,
            $basket->freeShippingCoupon => ShippingFreeReason::Coupon,
            default => null,
        };
        $fee = $freeReason === null ? $originalFee : 0;

        return new ShippingOption(
            reason: null,
            measure: $measure,
            rate: $rate,
            originalFee: $originalFee,
            fee: $fee,
            freeReason: $freeReason,
            feeOnDelivery: $terms->feeOnDelivery,
            remainingForFree: $terms->freeOverAmount !== null && $fee > 0 ? $terms->freeOverAmount - $total : null,
        );
    }
}

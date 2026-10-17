<?php

declare(strict_types=1);

namespace App\Actions\Shipping;

use App\Data\Coupons\CouponItem;
use App\Data\Coupons\CouponTerms;
use App\Data\Shipping\ShippingTerms;
use App\Data\Shipping\SimulatedShipping;
use App\Models\Coupon;
use App\Support\Pricing\CouponCalculator;
use App\Support\Pricing\ShippingCalculator;

final readonly class SimulateShippingAction
{
    public function __construct(
        private ShippingCalculator $shipping,
        private CouponCalculator $coupons,
    ) {}

    /**
     * The editor's unsaved method priced for a sample basket, by the rules checkout uses. A
     * coupon counts with its terms only (not its dates, limits or customers). Nothing is saved.
     *
     * @param  list<CouponItem>  $items
     */
    public function handle(ShippingTerms $terms, array $items, ?int $cityId, ?Coupon $coupon): SimulatedShipping
    {
        $couponQuote = $coupon !== null ? $this->coupons->quote(CouponTerms::of($coupon), $items) : null;
        $basket = $this->shipping->basket($items, $couponQuote);

        return new SimulatedShipping($basket, $this->shipping->quote($terms, $basket, $cityId), $couponQuote);
    }
}

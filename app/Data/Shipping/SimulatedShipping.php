<?php

declare(strict_types=1);

namespace App\Data\Shipping;

use App\Data\Coupons\CouponQuote;

/**
 * The editor's test box: the sample basket, the method worked out for it and the coupon (if one
 * was given) worked out for the same items.
 */
final readonly class SimulatedShipping
{
    public function __construct(
        public ShippingBasket $basket,
        public ShippingOption $option,
        public ?CouponQuote $coupon,
    ) {}
}

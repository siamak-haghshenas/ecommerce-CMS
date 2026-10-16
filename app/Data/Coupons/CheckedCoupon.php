<?php

declare(strict_types=1);

namespace App\Data\Coupons;

use App\Models\Coupon;

/**
 * A code a customer may use on their cart, with what it takes off.
 */
final readonly class CheckedCoupon
{
    public function __construct(
        public Coupon $coupon,
        public CouponQuote $quote,
    ) {}
}

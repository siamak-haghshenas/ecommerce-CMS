<?php

declare(strict_types=1);

namespace App\Data\Coupons;

/**
 * A cart line a coupon is worked out for.
 */
final readonly class CouponItem
{
    public function __construct(
        public int $variantId,
        public int $quantity,
    ) {}
}

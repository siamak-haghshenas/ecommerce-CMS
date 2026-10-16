<?php

declare(strict_types=1);

namespace App\Data\Coupons;

use App\Enums\CouponProblem;

/**
 * A coupon worked out for a cart. Amounts are Rials. With a problem the coupon takes nothing off.
 */
final readonly class CouponQuote
{
    /**
     * @param  list<CouponQuoteLine>  $lines  in the cart's order
     * @param  int  $uncappedDiscount  a percent discount before `max_discount` (else equal to $discount)
     */
    public function __construct(
        public array $lines,
        public int $subtotal,
        public int $eligibleSubtotal,
        public int $uncappedDiscount,
        public int $discount,
        public bool $freeShipping,
        public ?CouponProblem $problem,
    ) {}

    public function total(): int
    {
        return $this->subtotal - $this->discount;
    }
}

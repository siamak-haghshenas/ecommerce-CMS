<?php

declare(strict_types=1);

namespace App\Data\Coupons;

use App\Enums\CouponLineReason;

/**
 * One cart line with a coupon: its price now (sales and promotions applied) and whether the
 * coupon counts it. Amounts are Rials.
 */
final readonly class CouponQuoteLine
{
    public function __construct(
        public int $variantId,
        public int $quantity,
        public int $unitPrice,
        public int $total,
        public ?CouponLineReason $reason,
    ) {}

    public function isEligible(): bool
    {
        return $this->reason === null;
    }
}

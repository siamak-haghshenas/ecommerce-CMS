<?php

declare(strict_types=1);

namespace App\Data\Cart;

use App\Data\Coupons\CheckedCoupon;
use App\Exceptions\CouponException;

/**
 * The coupon on a cart, checked again on this read: what it takes off, or why it takes nothing
 * (the code stays on the cart until the customer removes it).
 */
final readonly class CartCoupon
{
    public function __construct(
        public string $code,
        public ?CheckedCoupon $checked,
        public ?CouponException $problem,
    ) {}

    /** Rials off the items. */
    public function discount(): int
    {
        return $this->checked?->quote->discount ?? 0;
    }

    public function freeShipping(): bool
    {
        return $this->checked?->quote->freeShipping ?? false;
    }
}

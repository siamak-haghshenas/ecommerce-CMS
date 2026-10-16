<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Why a coupon takes nothing off a cart.
 */
enum CouponProblem: string
{
    /** No line of the cart is eligible. */
    case NoEligibleItems = 'no_eligible_items';
    /** The eligible lines add up to less than `min_order_amount`. */
    case MinOrder = 'min_order';
}

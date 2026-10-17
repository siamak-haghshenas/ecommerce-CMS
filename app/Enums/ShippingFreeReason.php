<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Why a shipping fee became 0. When both apply, the order's total is given (`FreeOver`), so the
 * coupon isn't credited with the saving.
 */
enum ShippingFreeReason: string
{
    /** The order reached the method's "free over" amount. */
    case FreeOver = 'free_over';

    /** A free-shipping coupon. */
    case Coupon = 'coupon';
}

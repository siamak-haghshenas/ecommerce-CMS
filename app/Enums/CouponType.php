<?php

declare(strict_types=1);

namespace App\Enums;

enum CouponType: string
{
    /** `value` is a percentage (1–90) of the eligible items, optionally capped by `max_discount`. */
    case Percent = 'percent';
    /** `value` is an amount in Rials taken off the eligible items. */
    case Fixed = 'fixed';
    /** The order ships for free; `value` is null. */
    case FreeShipping = 'free_shipping';
}

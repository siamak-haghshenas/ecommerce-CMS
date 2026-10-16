<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Which items a coupon takes its discount off.
 */
enum CouponAppliesTo: string
{
    /** Every product (excluded products left out). */
    case All = 'all';
    /** Products in the chosen categories (sub-categories included), brands or tags, or chosen themselves. */
    case Selected = 'selected';
}

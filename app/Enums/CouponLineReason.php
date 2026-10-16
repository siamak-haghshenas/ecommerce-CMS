<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Why a cart line gets no coupon discount (checked in this order).
 */
enum CouponLineReason: string
{
    /** The product is one of the coupon's excluded products. */
    case Excluded = 'excluded';
    /** The coupon is for chosen items and this isn't one of them. */
    case NotTargeted = 'not_targeted';
    /** A sale, sale event or promotion prices it and the coupon leaves discounted items out. */
    case Discounted = 'discounted';
}

<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Who may use a coupon.
 */
enum CouponCustomerScope: string
{
    /** Every logged-in customer. */
    case All = 'all';
    /** Only the customers linked in `coupon_customer`; others get "this code isn't for your account". */
    case Selected = 'selected';
}

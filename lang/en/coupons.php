<?php

declare(strict_types=1);

return [

    // Storefront code check (POST /api/v1/coupons/check).
    'errors' => [
        'coupon_not_found' => 'This discount code doesn’t exist.',
        'coupon_inactive' => 'This discount code isn’t active.',
        'coupon_not_started' => 'This discount code can’t be used yet.',
        'coupon_expired' => 'This discount code has expired.',
        'coupon_limit_reached' => 'This discount code has been used up.',
        'coupon_not_for_customer' => 'This discount code isn’t for your account.',
        'coupon_min_order' => 'This discount code needs an order of at least :amount Rials of eligible items.',
        'coupon_no_eligible_items' => 'None of the items in your cart can use this discount code.',
        'item_unavailable' => 'This item isn’t available.',
    ],

];

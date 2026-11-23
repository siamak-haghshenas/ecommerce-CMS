<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Shopping Cart Settings
    |--------------------------------------------------------------------------
    |
    | Carts belong to a customer or to a guest (found by the token sent in the
    | X-Cart-Token header). Nothing is reserved: prices, stock and limits are
    | checked again on every read.
    |
    */

    // The most units of one variant a cart line may hold (stock and order limits may lower it).
    'max_quantity_per_line' => (int) env('CART_MAX_QUANTITY_PER_LINE', 100),

    // The most lines (different variants) in one cart.
    'max_lines' => (int) env('CART_MAX_LINES', 100),

    // Carts untouched for this many days are removed by model:prune.
    'prune_after_days' => [
        'guest' => (int) env('CART_GUEST_DAYS', 30),
        'customer' => (int) env('CART_CUSTOMER_DAYS', 90),
    ],

];

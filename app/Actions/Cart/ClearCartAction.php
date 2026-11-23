<?php

declare(strict_types=1);

namespace App\Actions\Cart;

use App\Models\Cart;

final readonly class ClearCartAction
{
    /**
     * Empty the cart: every line and the coupon. The cart itself (and a guest's token) stays.
     */
    public function handle(Cart $cart): void
    {
        if (! $cart->exists) {
            return;
        }

        $cart->items()->delete();
        $cart->update(['coupon_code' => null, 'last_activity_at' => now()]);
    }
}

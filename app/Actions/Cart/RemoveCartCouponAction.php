<?php

declare(strict_types=1);

namespace App\Actions\Cart;

use App\Models\Cart;

final readonly class RemoveCartCouponAction
{
    public function handle(Cart $cart): void
    {
        if ($cart->exists && $cart->coupon_code !== null) {
            $cart->update(['coupon_code' => null, 'last_activity_at' => now()]);
        }
    }
}

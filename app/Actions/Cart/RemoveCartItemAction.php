<?php

declare(strict_types=1);

namespace App\Actions\Cart;

use App\Models\Cart;

final readonly class RemoveCartItemAction
{
    /**
     * Take a variant out of the cart (nothing happens if it isn't there).
     */
    public function handle(Cart $cart, int $variantId): void
    {
        if (! $cart->exists) {
            return;
        }

        $cart->items()->where('product_variant_id', $variantId)->delete();
        $cart->update(['last_activity_at' => now()]);
    }
}

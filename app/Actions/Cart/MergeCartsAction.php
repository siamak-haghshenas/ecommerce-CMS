<?php

declare(strict_types=1);

namespace App\Actions\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\User;
use App\Support\Cart\CartCatalog;
use Illuminate\Support\Facades\DB;

final readonly class MergeCartsAction
{
    /**
     * Move a guest cart into the customer's after they log in, then delete it. A variant in both
     * carts gets both quantities; a line that can be bought is cut to its limit (stock, the
     * price's order limit, the store's cap) and remembers what it asked for (`limited_from`), so
     * the customer sees the `exceeds_limit` notice. Lines that can't be bought keep their
     * quantity and show their problem. The customer cart's coupon stays; without one, the guest
     * cart's is taken (both are checked again on every read).
     *
     * An unknown token (already merged, pruned) changes nothing.
     */
    public function handle(User $customer, string $token): Cart
    {
        return DB::transaction(function () use ($customer, $token): Cart {
            $cart = Cart::query()->createOrFirst(['user_id' => $customer->id], ['last_activity_at' => now()]);
            Cart::query()->whereKey($cart->id)->lockForUpdate()->first();

            $guest = Cart::query()
                ->whereNull('user_id')
                ->where('token_hash', Cart::hashToken($token))
                ->lockForUpdate()
                ->first();

            if ($guest === null) {
                return $cart;
            }

            $guestItems = $guest->items()->get();
            $existing = $cart->items()->get()->keyBy('product_variant_id');
            $catalog = CartCatalog::load($guestItems->pluck('product_variant_id')->all());
            $maxLines = (int) config('cart.max_lines');
            $lines = $existing->count();

            foreach ($guestItems as $guestItem) {
                $item = $existing->get($guestItem->product_variant_id);

                if ($item === null && $lines >= $maxLines) {
                    continue;
                }

                $wanted = ($item->quantity ?? 0) + $guestItem->quantity;
                $max = $catalog->maxQuantity($guestItem->product_variant_id);
                $limited = $catalog->problem($guestItem->product_variant_id) === null && $wanted > $max;

                if ($item === null) {
                    $lines++;
                    $item = new CartItem([
                        'cart_id' => $cart->id,
                        'product_variant_id' => $guestItem->product_variant_id,
                        'unit_price_seen' => $guestItem->unit_price_seen,
                    ]);
                }

                $item->fill([
                    'quantity' => $limited ? $max : $wanted,
                    'limited_from' => $limited ? $wanted : $item->limited_from,
                ])->save();
            }

            $cart->update([
                'coupon_code' => $cart->coupon_code ?? $guest->coupon_code,
                'last_activity_at' => now(),
            ]);
            $guest->delete();

            return $cart;
        });
    }
}

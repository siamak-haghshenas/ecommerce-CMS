<?php

declare(strict_types=1);

namespace App\Actions\Cart;

use App\Data\Cart\ResolvedCart;
use App\Models\Cart;
use App\Models\User;
use Illuminate\Support\Str;

final readonly class ResolveCartAction
{
    /**
     * The cart a request works on: the logged-in customer's, else the guest cart of the token
     * (an unknown token counts as none). Without one, `$open` opens it (a guest gets a new
     * token); otherwise an unsaved, empty cart is returned.
     */
    public function handle(?User $customer, ?string $token, bool $open = false): ResolvedCart
    {
        if ($customer !== null) {
            $cart = $customer->cart;

            if ($cart === null && $open) {
                $cart = Cart::query()->createOrFirst(['user_id' => $customer->id], ['last_activity_at' => now()]);
            }

            return new ResolvedCart($cart === null ? new Cart(['user_id' => $customer->id]) : $this->touch($cart));
        }

        $cart = $token === null || $token === ''
            ? null
            : Cart::query()->whereNull('user_id')->where('token_hash', Cart::hashToken($token))->first();

        if ($cart !== null) {
            return new ResolvedCart($this->touch($cart));
        }

        if (! $open) {
            return new ResolvedCart(new Cart);
        }

        $token = Str::random(48);

        return new ResolvedCart(Cart::query()->create(['token_hash' => Cart::hashToken($token), 'last_activity_at' => now()]), $token);
    }

    /**
     * Keep a cart in use from being pruned (at most one write an hour).
     */
    private function touch(Cart $cart): Cart
    {
        if ($cart->last_activity_at->lt(now()->minus(hours: 1))) {
            $cart->update(['last_activity_at' => now()]);
        }

        return $cart;
    }
}

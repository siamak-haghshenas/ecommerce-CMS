<?php

declare(strict_types=1);

namespace App\Actions\Cart;

use App\Enums\CartLineProblem;
use App\Exceptions\CartException;
use App\Models\Cart;
use App\Support\Cart\CartCatalog;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

final readonly class SetCartItemQuantityAction
{
    /**
     * Change how many units of a variant the cart holds. The variant must still be on sale and
     * the new quantity within the line's limit (so a line above its limit can always be lowered
     * to it). Clears a merge's "cut to the limit" notice.
     *
     * @throws CartException
     * @throws ModelNotFoundException the variant is not in the cart
     */
    public function handle(Cart $cart, int $variantId, int $quantity): void
    {
        DB::transaction(function () use ($cart, $variantId, $quantity): void {
            Cart::query()->whereKey($cart->id)->lockForUpdate()->first();

            $item = $cart->items()->where('product_variant_id', $variantId)->firstOrFail();
            $catalog = CartCatalog::load([$variantId]);

            match ($catalog->problem($variantId)) {
                CartLineProblem::Unavailable => throw CartException::itemUnavailable(),
                CartLineProblem::OutOfStock => throw CartException::itemOutOfStock(),
                default => null,
            };

            $max = $catalog->maxQuantity($variantId);

            if ($quantity > $max) {
                throw CartException::quantityExceeded($max);
            }

            $item->update(['quantity' => $quantity, 'limited_from' => null]);
            $cart->update(['last_activity_at' => now()]);
        });
    }
}

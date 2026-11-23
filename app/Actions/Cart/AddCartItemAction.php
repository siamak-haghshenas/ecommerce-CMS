<?php

declare(strict_types=1);

namespace App\Actions\Cart;

use App\Enums\CartLineProblem;
use App\Exceptions\CartException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Support\Cart\CartCatalog;
use Illuminate\Support\Facades\DB;

final readonly class AddCartItemAction
{
    /**
     * Put units of a variant in the cart, on top of the ones already there. The variant must be
     * on sale and the line stay within its limit; a new line must fit in the cart.
     *
     * @throws CartException
     */
    public function handle(Cart $cart, int $variantId, int $quantity): void
    {
        DB::transaction(function () use ($cart, $variantId, $quantity): void {
            // One change at a time per cart, so two quick adds can't pass the limit together.
            Cart::query()->whereKey($cart->id)->lockForUpdate()->first();

            $catalog = CartCatalog::load([$variantId]);

            match ($catalog->problem($variantId)) {
                CartLineProblem::Unavailable => throw CartException::itemUnavailable(),
                CartLineProblem::OutOfStock => throw CartException::itemOutOfStock(),
                default => null,
            };

            $item = $cart->items()->where('product_variant_id', $variantId)->first();
            $maxLines = (int) config('cart.max_lines');

            if ($item === null && $cart->items()->count() >= $maxLines) {
                throw CartException::full($maxLines);
            }

            $total = ($item->quantity ?? 0) + $quantity;
            $max = $catalog->maxQuantity($variantId);

            if ($total > $max) {
                throw CartException::quantityExceeded($max);
            }

            $variant = $catalog->variant($variantId);
            assert($variant !== null);

            // The customer just saw the product page: its price is the one they know.
            CartItem::query()->updateOrCreate(
                ['cart_id' => $cart->id, 'product_variant_id' => $variantId],
                ['quantity' => $total, 'unit_price_seen' => $variant->currentPrice(), 'limited_from' => null],
            );

            $cart->update(['last_activity_at' => now()]);
        });
    }
}

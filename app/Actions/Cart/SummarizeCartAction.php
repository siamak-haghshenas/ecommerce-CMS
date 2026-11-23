<?php

declare(strict_types=1);

namespace App\Actions\Cart;

use App\Actions\Coupons\CheckCouponAction;
use App\Data\Cart\CartCoupon;
use App\Data\Cart\CartLine;
use App\Data\Cart\CartSummary;
use App\Enums\CartLineProblem;
use App\Exceptions\CouponException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Support\Cart\CartCatalog;

final readonly class SummarizeCartAction
{
    public function __construct(private CheckCouponAction $checkCoupon) {}

    /**
     * The cart as the storefront sells it now: each line at the current price with its limit
     * and problems, the coupon checked again (a coupon that no longer applies keeps its code and
     * reports why) and the totals. Nothing is changed or removed, except that a changed price is
     * shown once: the line then remembers the new price as seen.
     */
    public function handle(Cart $cart): CartSummary
    {
        $items = $cart->exists ? $cart->items()->get() : collect();
        $catalog = CartCatalog::load($items->pluck('product_variant_id')->all(), withDetails: true);

        $lines = $items->map(function (CartItem $item) use ($catalog): CartLine {
            $variantId = $item->product_variant_id;
            $variant = $catalog->variant($variantId);
            $problem = $catalog->problem($variantId);
            $max = $catalog->maxQuantity($variantId);
            $unitPrice = $variant?->currentPrice() ?? $item->unit_price_seen;
            $problems = $problem === null ? [] : [$problem];
            $priceWas = null;

            if ($problem === null) {
                if ($item->quantity > $max || $item->limited_from !== null) {
                    $problems[] = CartLineProblem::ExceedsLimit;
                }

                if ($unitPrice !== $item->unit_price_seen) {
                    $problems[] = CartLineProblem::PriceChanged;
                    $priceWas = $item->unit_price_seen;
                    $item->update(['unit_price_seen' => $unitPrice]);
                }
            }

            return new CartLine($item, $variant, $max, $unitPrice, $variant->price ?? $unitPrice, $priceWas, $problems);
        })->values()->all();

        $summary = new CartSummary($cart, $lines, null);

        return new CartSummary($cart, $lines, $this->coupon($cart, $summary));
    }

    private function coupon(Cart $cart, CartSummary $summary): ?CartCoupon
    {
        $customer = $cart->user;

        if ($cart->coupon_code === null || $customer === null) {
            return null;
        }

        try {
            return new CartCoupon($cart->coupon_code, $this->checkCoupon->handle($customer, $cart->coupon_code, $summary->purchasableItems()), null);
        } catch (CouponException $problem) {
            return new CartCoupon($cart->coupon_code, null, $problem);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Cart;

use App\Actions\Coupons\CheckCouponAction;
use App\Data\Coupons\CouponItem;
use App\Exceptions\CartException;
use App\Exceptions\CouponException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Support\Cart\CartCatalog;

final readonly class ApplyCartCouponAction
{
    public function __construct(private CheckCouponAction $checkCoupon) {}

    /**
     * Put a coupon on a customer's cart, after checking it against the lines that can be bought
     * like `coupons/check` (its errors are thrown and nothing is saved). It replaces the cart's
     * coupon, if any; one coupon per order.
     *
     * @param  string  $code  normalized like the admin saves it
     *
     * @throws CartException a guest cart: coupons depend on the customer
     * @throws CouponException
     */
    public function handle(Cart $cart, string $code): void
    {
        $customer = $cart->user;

        if ($customer === null) {
            throw CartException::loginRequired();
        }

        $items = $cart->exists ? $cart->items()->get() : collect();
        $catalog = CartCatalog::load($items->pluck('product_variant_id')->all());
        $purchasable = $items
            ->filter(fn (CartItem $item): bool => $catalog->problem($item->product_variant_id) === null)
            ->map(fn (CartItem $item): CouponItem => new CouponItem($item->product_variant_id, $item->quantity))
            ->values()
            ->all();

        $checked = $this->checkCoupon->handle($customer, $code, $purchasable);

        $cart->forceFill(['coupon_code' => $checked->coupon->code, 'last_activity_at' => now()])->save();
    }
}

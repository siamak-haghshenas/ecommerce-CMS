<?php

declare(strict_types=1);

namespace App\Actions\Shipping;

use App\Actions\Cart\SummarizeCartAction;
use App\Actions\Coupons\CheckCouponAction;
use App\Data\Coupons\CouponItem;
use App\Data\Shipping\QuotedShipping;
use App\Data\Shipping\ShippingQuote;
use App\Data\Shipping\ShippingTerms;
use App\Exceptions\CouponException;
use App\Models\Cart;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Support\Pricing\ShippingCalculator;

final readonly class QuoteShippingAction
{
    public function __construct(
        private ShippingCalculator $calculator,
        private CheckCouponAction $checkCoupon,
        private SummarizeCartAction $summarizeCart,
    ) {}

    /**
     * The methods a customer can choose for their cart and city, in the admin's order, with
     * their fees. A coupon code is checked like `coupons/check` (its errors are thrown); its
     * discount counts toward "free over" and the minimum order, and a free-shipping coupon makes
     * every fee 0.
     *
     * Without items, the customer's cart is used: the lines that can be bought, and the cart's
     * coupon when it applies (one that doesn't takes nothing off, like in the cart).
     *
     * @param  list<CouponItem>|null  $items
     *
     * @throws CouponException
     */
    public function handle(User $customer, int $cityId, ?array $items, ?string $couponCode): ShippingQuote
    {
        if ($items === null) {
            $cart = $this->summarizeCart->handle($customer->cart ?? new Cart(['user_id' => $customer->id]));
            $items = $cart->purchasableItems();
            $coupon = $cart->coupon?->checked?->quote;
        } else {
            $coupon = $couponCode !== null ? $this->checkCoupon->handle($customer, $couponCode, $items)->quote : null;
        }

        $basket = $this->calculator->basket($items, $coupon);

        $methods = ShippingMethod::query()
            ->where('is_active', true)
            ->ordered()
            ->with(['rates', 'cities' => fn ($query) => $query->whereKey($cityId)])
            ->get();

        $options = [];

        foreach ($methods as $method) {
            $option = $this->calculator->quote(ShippingTerms::of($method), $basket, $cityId);

            if ($option->isAvailable()) {
                $options[] = new QuotedShipping($method, $option);
            }
        }

        return new ShippingQuote($basket, $options);
    }
}

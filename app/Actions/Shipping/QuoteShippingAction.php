<?php

declare(strict_types=1);

namespace App\Actions\Shipping;

use App\Actions\Coupons\CheckCouponAction;
use App\Data\Coupons\CouponItem;
use App\Data\Shipping\QuotedShipping;
use App\Data\Shipping\ShippingQuote;
use App\Data\Shipping\ShippingTerms;
use App\Exceptions\CouponException;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Support\Pricing\ShippingCalculator;

final readonly class QuoteShippingAction
{
    public function __construct(
        private ShippingCalculator $calculator,
        private CheckCouponAction $checkCoupon,
    ) {}

    /**
     * The methods a customer can choose for their cart and city, in the admin's order, with
     * their fees. A coupon code is checked like `coupons/check` (its errors are thrown); its
     * discount counts toward "free over" and the minimum order, and a free-shipping coupon makes
     * every fee 0.
     *
     * @param  list<CouponItem>  $items
     *
     * @throws CouponException
     */
    public function handle(User $customer, int $cityId, array $items, ?string $couponCode): ShippingQuote
    {
        $coupon = $couponCode !== null ? $this->checkCoupon->handle($customer, $couponCode, $items)->quote : null;
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

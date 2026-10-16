<?php

declare(strict_types=1);

namespace App\Actions\Coupons;

use App\Data\Coupons\CheckedCoupon;
use App\Data\Coupons\CouponItem;
use App\Data\Coupons\CouponTerms;
use App\Enums\CouponProblem;
use App\Exceptions\CouponException;
use App\Models\Coupon;
use App\Models\User;
use App\Support\Pricing\CouponCalculator;

final readonly class CheckCouponAction
{
    public function __construct(private CouponCalculator $calculator) {}

    /**
     * Whether a customer may use a code on their cart, and what it takes off. Checked in this
     * order: the code exists, it is for this customer, its state (disabled, expired, used up, not
     * started), then the cart (eligible items, minimum order).
     *
     * The per-customer limit and "first order only" need orders: until they exist, every
     * customer is on their first order and hasn't used the code.
     *
     * @param  string  $code  normalized like the admin saves it
     * @param  list<CouponItem>  $items
     *
     * @throws CouponException
     */
    public function handle(User $customer, string $code, array $items): CheckedCoupon
    {
        $coupon = Coupon::query()->where('code', $code)->first() ?? throw CouponException::notFound();

        if (! $coupon->isFor($customer)) {
            throw CouponException::notForCustomer();
        }

        match ($coupon->state()) {
            'disabled' => throw CouponException::inactive(),
            'expired' => throw CouponException::expired(),
            'used_up' => throw CouponException::limitReached(),
            'scheduled' => throw CouponException::notStarted((string) $coupon->starts_at?->toIso8601String()),
            default => null,
        };

        $quote = $this->calculator->quote(CouponTerms::of($coupon), $items);

        match ($quote->problem) {
            CouponProblem::NoEligibleItems => throw CouponException::noEligibleItems(),
            CouponProblem::MinOrder => throw CouponException::minOrder((int) $coupon->min_order_amount),
            null => null,
        };

        return new CheckedCoupon($coupon, $quote);
    }
}

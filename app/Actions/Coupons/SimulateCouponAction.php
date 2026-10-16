<?php

declare(strict_types=1);

namespace App\Actions\Coupons;

use App\Data\Coupons\CouponItem;
use App\Data\Coupons\CouponQuote;
use App\Data\Coupons\CouponTerms;
use App\Support\Pricing\CouponCalculator;

final readonly class SimulateCouponAction
{
    public function __construct(private CouponCalculator $calculator) {}

    /**
     * The editor's unsaved coupon applied to a sample cart, by the rules checkout uses. Its
     * dates, limits and customers are left out: the sample shows what it takes off. Nothing is saved.
     *
     * @param  list<CouponItem>  $items
     */
    public function handle(CouponTerms $terms, array $items): CouponQuote
    {
        return $this->calculator->quote($terms, $items);
    }
}

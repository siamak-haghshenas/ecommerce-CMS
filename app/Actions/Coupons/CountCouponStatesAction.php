<?php

declare(strict_types=1);

namespace App\Actions\Coupons;

use App\Data\Coupons\CouponFilters;
use App\Models\Coupon;

final class CountCouponStatesAction
{
    /**
     * How many coupons the search matches in each state (the status filter left out), for the
     * list's status chips.
     *
     * @return array<string, int>
     */
    public function handle(CouponFilters $filters): array
    {
        $counts = ['all' => Coupon::query()->matching($filters->search)->count()];

        foreach (Coupon::STATES as $state) {
            $counts[$state] = Coupon::query()->matching($filters->search)->inState($state)->count();
        }

        return $counts;
    }
}

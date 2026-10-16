<?php

declare(strict_types=1);

namespace App\Actions\Coupons;

use App\Data\Coupons\CouponFilters;
use App\Models\Coupon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class SearchCouponsAction
{
    /**
     * The admin coupon list, newest first, with how many items and customers each is limited to.
     *
     * @return LengthAwarePaginator<int, Coupon>
     */
    public function handle(CouponFilters $filters): LengthAwarePaginator
    {
        return Coupon::query()
            ->matching($filters->search)
            ->when($filters->state !== null, fn (Builder $query) => $query->inState((string) $filters->state))
            ->withCount(['categories', 'brands', 'tags', 'products', 'excludedProducts', 'customers'])
            ->latest('id')
            ->paginate($filters->perPage);
    }
}

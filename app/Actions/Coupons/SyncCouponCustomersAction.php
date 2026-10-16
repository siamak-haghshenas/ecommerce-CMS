<?php

declare(strict_types=1);

namespace App\Actions\Coupons;

use App\Enums\CouponCustomerScope;
use App\Models\Coupon;

final class SyncCouponCustomersAction
{
    /**
     * Save who may use a coupon: everyone (the list is emptied) or the given customers. Called by
     * the coupon save inside its transaction; the ids are checked by the request first.
     *
     * @param  list<int>  $customerIds
     * @return bool whether the scope or the list changed (the audit log names `customers`)
     */
    public function handle(Coupon $coupon, CouponCustomerScope $scope, array $customerIds): bool
    {
        $ids = $scope === CouponCustomerScope::Selected ? array_values(array_unique($customerIds)) : [];
        $scopeChanged = $coupon->customer_scope !== $scope;

        if ($scopeChanged) {
            $coupon->forceFill(['customer_scope' => $scope])->save();
        }

        $changes = $coupon->customers()->sync($ids);

        return $scopeChanged || $changes['attached'] !== [] || $changes['detached'] !== [];
    }
}

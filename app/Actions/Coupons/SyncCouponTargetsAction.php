<?php

declare(strict_types=1);

namespace App\Actions\Coupons;

use App\Data\Coupons\CouponTerms;
use App\Models\Coupon;

final class SyncCouponTargetsAction
{
    /**
     * Save the items a coupon is for (empty lists for `applies_to` `all`) and its excluded
     * products. Called by the coupon save inside its transaction; the ids are checked by the
     * request first.
     *
     * @return list<string> the changed lists for the audit log: `targets`, `excluded_products`
     */
    public function handle(Coupon $coupon, CouponTerms $terms): array
    {
        $changed = false;

        foreach (['categories' => $terms->categoryIds, 'brands' => $terms->brandIds, 'tags' => $terms->tagIds, 'products' => $terms->productIds] as $relation => $ids) {
            $changes = $coupon->{$relation}()->sync(array_values(array_unique($ids)));
            $changed = $changed || $changes['attached'] !== [] || $changes['detached'] !== [];
        }

        $excluded = $coupon->excludedProducts()->sync(array_values(array_unique($terms->excludedProductIds)));

        return array_values(array_filter([
            $changed ? 'targets' : null,
            $excluded['attached'] !== [] || $excluded['detached'] !== [] ? 'excluded_products' : null,
        ]));
    }
}

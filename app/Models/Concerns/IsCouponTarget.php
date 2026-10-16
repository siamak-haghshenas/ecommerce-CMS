<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Coupon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Something a coupon can be limited to (a category, brand or tag; links in `coupon_targets`).
 * Deleting it removes it from those coupons, which then apply to fewer items.
 *
 * @mixin Model
 */
trait IsCouponTarget
{
    public static function bootIsCouponTarget(): void
    {
        static::deleted(function (self $model): void {
            $model->coupons()->detach();
        });
    }

    /**
     * The coupons limited to this item, deleted ones included (they keep their settings).
     *
     * @return MorphToMany<Coupon, $this>
     */
    public function coupons(): MorphToMany
    {
        return $this->morphToMany(Coupon::class, 'target', 'coupon_targets')->withTrashed();
    }
}

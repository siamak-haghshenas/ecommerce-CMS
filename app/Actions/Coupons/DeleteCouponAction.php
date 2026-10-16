<?php

declare(strict_types=1);

namespace App\Actions\Coupons;

use App\Exceptions\CouponException;
use App\Models\Coupon;
use App\Models\User;

final class DeleteCouponAction
{
    /**
     * Delete a coupon no order has used (it goes to the trash, so its code stays taken).
     *
     * @throws CouponException when an order used it
     */
    public function handle(User $actor, Coupon $coupon): void
    {
        if ($coupon->used_count > 0) {
            throw CouponException::inUse();
        }

        $coupon->delete();

        activity('admin')
            ->causedBy($actor)
            ->event('coupon.deleted')
            ->withProperties(['code' => $coupon->code])
            ->log('coupon.deleted');
    }
}

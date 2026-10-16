<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Storefront\Coupons;

use App\Actions\Coupons\CheckCouponAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Storefront\Coupons\CheckCouponRequest;
use App\Http\Resources\V1\Storefront\CouponCheckResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;

class CouponCheckController extends Controller
{
    /**
     * Whether the logged-in customer may use a code on their cart and what it takes off, or a
     * coupon error code. Nothing is saved.
     */
    public function __invoke(CheckCouponRequest $request, CheckCouponAction $check, #[CurrentUser] User $customer): CouponCheckResource
    {
        return new CouponCheckResource($check->handle($customer, $request->couponCode(), $request->cartItems()));
    }
}

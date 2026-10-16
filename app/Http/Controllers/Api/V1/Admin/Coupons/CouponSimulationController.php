<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Coupons;

use App\Actions\Coupons\SimulateCouponAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Coupons\SimulateCouponRequest;
use App\Http\Resources\V1\CouponQuoteResource;

class CouponSimulationController extends Controller
{
    /**
     * The editor's sample order: the unsaved coupon applied to sample items. Nothing is saved.
     */
    public function __invoke(SimulateCouponRequest $request, SimulateCouponAction $simulate): CouponQuoteResource
    {
        return new CouponQuoteResource($simulate->handle($request->terms(), $request->cartItems()));
    }
}

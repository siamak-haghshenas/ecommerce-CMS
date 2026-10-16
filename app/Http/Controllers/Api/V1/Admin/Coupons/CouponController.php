<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Coupons;

use App\Actions\Coupons\CountCouponStatesAction;
use App\Actions\Coupons\DeleteCouponAction;
use App\Actions\Coupons\SaveCouponAction;
use App\Actions\Coupons\SearchCouponsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Coupons\IndexCouponsRequest;
use App\Http\Requests\Api\V1\Admin\Coupons\SaveCouponRequest;
use App\Http\Resources\V1\Admin\CouponResource;
use App\Models\Coupon;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CouponController extends Controller
{
    /**
     * Coupons, paginated, with search and status; `meta.status_counts` counts each status.
     */
    public function index(IndexCouponsRequest $request, SearchCouponsAction $search, CountCouponStatesAction $count): AnonymousResourceCollection
    {
        $filters = $request->toFilters();

        return CouponResource::collection($search->handle($filters))
            ->additional(['meta' => ['status_counts' => $count->handle($filters)]]);
    }

    /**
     * The coupon with the customers it is limited to.
     */
    public function show(Coupon $coupon): CouponResource
    {
        return new CouponResource($coupon->load(CouponResource::detailRelations()));
    }

    public function store(SaveCouponRequest $request, SaveCouponAction $save, #[CurrentUser] User $actor): CouponResource
    {
        return $this->show($save->handle($actor, null, $request->toData()));
    }

    public function update(SaveCouponRequest $request, Coupon $coupon, SaveCouponAction $save, #[CurrentUser] User $actor): CouponResource
    {
        return $this->show($save->handle($actor, $coupon, $request->toData()));
    }

    /**
     * Refused (409 `coupon_in_use`) once an order used it.
     */
    public function destroy(Coupon $coupon, DeleteCouponAction $delete, #[CurrentUser] User $actor): Response
    {
        $delete->handle($actor, $coupon);

        return response()->noContent();
    }
}

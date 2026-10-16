<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Storefront;

use App\Data\Coupons\CheckedCoupon;
use App\Http\Resources\V1\CouponQuoteResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A code the customer may use on their cart: the coupon's public terms and what it takes off.
 * The coupon's name, limits and customers are admin-only. Amounts are Rials.
 *
 * @property CheckedCoupon $resource
 */
class CouponCheckResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $coupon = $this->resource->coupon;

        return [
            'code' => $coupon->code,
            'type' => $coupon->type->value,
            'value' => $coupon->value,
            'max_discount' => $coupon->max_discount,
            'min_order_amount' => $coupon->min_order_amount,
            'ends_at' => $coupon->ends_at?->toIso8601String(),
            ...(new CouponQuoteResource($this->resource->quote))->toArray($request),
        ];
    }
}

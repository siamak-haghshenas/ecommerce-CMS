<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Admin;

use App\Data\Shipping\ShippingBasketLine;
use App\Data\Shipping\SimulatedShipping;
use App\Http\Resources\V1\ShippingOptionResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The editor's test box: each line's price, weight and volume, the basket's totals and the
 * method worked out for it. Amounts are Rials; weights grams, volumes cm³.
 *
 * @property SimulatedShipping $resource
 */
class ShippingSimulationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $basket = $this->resource->basket;

        return [
            'lines' => array_map(fn (ShippingBasketLine $line): array => [
                'variant_id' => $line->variantId,
                'quantity' => $line->quantity,
                'unit_price' => $line->unitPrice,
                'unit_weight_grams' => $line->unitWeightGrams,
                'uses_default_weight' => $line->usesDefaultWeight,
                'unit_volume_cm3' => $line->unitVolumeCm3,
            ], $basket->lines),
            'subtotal' => $basket->subtotal,
            'coupon_discount' => $basket->couponDiscount,
            'coupon_problem' => $this->resource->coupon?->problem?->value,
            'free_shipping_coupon' => $basket->freeShippingCoupon,
            'items_total' => $basket->total(),
            'weight_grams' => $basket->weightGrams,
            'volume_cm3' => $basket->volumeCm3,
            'shipping' => new ShippingOptionResource($this->resource->option),
        ];
    }
}

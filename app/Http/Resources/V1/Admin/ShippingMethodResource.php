<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Admin;

use App\Models\City;
use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A shipping method with its ranges (grams or cm³) and courier cities. Amounts are Rials.
 *
 * @mixin ShippingMethod
 */
class ShippingMethodResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type->value,
            'fee_on_delivery' => $this->fee_on_delivery,
            'default_fee' => $this->default_fee,
            'packaging_weight_grams' => $this->packaging_weight_grams,
            'min_order_amount' => $this->min_order_amount,
            'free_over_amount' => $this->free_over_amount,
            'delivery_days_min' => $this->delivery_days_min,
            'delivery_days_max' => $this->delivery_days_max,
            'is_active' => $this->is_active,
            'position' => $this->position,
            'rates' => $this->rates->map(fn (ShippingRate $rate): array => [
                'start' => $rate->range_start,
                'end' => $rate->range_end,
                'fee' => $rate->fee,
            ])->values()->all(),
            'cities' => $this->cities->sortBy([['province.position', 'asc'], ['name', 'asc']])->map(fn (City $city): array => [
                'id' => $city->id,
                'name' => $city->name,
                'province' => ['id' => $city->province->id, 'name' => $city->province->name],
                'fee' => $city->shippingFee(),
            ])->values()->all(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

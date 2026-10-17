<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Data\Shipping\ShippingOption;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A shipping method worked out for a cart (the admin's test box, the storefront's quote).
 * Amounts are Rials; `measure` is grams (weight methods, packaging included) or cm³ (volume).
 *
 * @property ShippingOption $resource
 */
class ShippingOptionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $option = $this->resource;

        return [
            'available' => $option->isAvailable(),
            'reason' => $option->reason?->value,
            'measure' => $option->measure,
            'range' => $option->rate === null ? null : [
                'start' => $option->rate->start,
                'end' => $option->rate->end,
                'fee' => $option->rate->fee,
            ],
            'original_fee' => $option->originalFee,
            'fee' => $option->fee,
            'free_reason' => $option->freeReason?->value,
            'fee_on_delivery' => $option->feeOnDelivery,
            'payable_now' => $option->isAvailable() ? $option->payableNow() : null,
            'collect_on_delivery' => $option->isAvailable() ? $option->collectOnDelivery() : null,
            'remaining_for_free' => $option->remainingForFree,
        ];
    }
}

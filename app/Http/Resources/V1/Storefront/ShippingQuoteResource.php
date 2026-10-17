<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Storefront;

use App\Data\Shipping\QuotedShipping;
use App\Data\Shipping\ShippingQuote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The methods a customer can choose, in the admin's order. Amounts are Rials. `fee` is what
 * shipping costs (0 when free, `original_fee` before that); `payable_now` is paid online with the
 * order and `collect_on_delivery` is paid to the carrier (`fee_on_delivery`). Unavailable methods
 * are left out.
 *
 * @property ShippingQuote $resource
 */
class ShippingQuoteResource extends JsonResource
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
            'items_subtotal' => $basket->subtotal,
            'coupon_discount' => $basket->couponDiscount,
            'items_total' => $basket->total(),
            'methods' => array_map(function (QuotedShipping $quoted): array {
                $method = $quoted->method;
                $option = $quoted->option;

                return [
                    'id' => $method->id,
                    'name' => $method->name,
                    'description' => $method->description,
                    'type' => $method->type->value,
                    'delivery_days' => ['min' => $method->delivery_days_min, 'max' => $method->delivery_days_max],
                    'original_fee' => $option->originalFee,
                    'fee' => $option->fee,
                    'free_reason' => $option->freeReason?->value,
                    'fee_on_delivery' => $option->feeOnDelivery,
                    'payable_now' => $option->payableNow(),
                    'collect_on_delivery' => $option->collectOnDelivery(),
                    'free_over_amount' => $method->free_over_amount,
                    'remaining_for_free' => $option->remainingForFree,
                ];
            }, $this->resource->options),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Data\Coupons\CouponQuote;
use App\Data\Coupons\CouponQuoteLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A coupon worked out for a cart (the admin's sample order, the storefront's code check).
 * Amounts are Rials.
 *
 * @property CouponQuote $resource
 */
class CouponQuoteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $quote = $this->resource;

        return [
            'lines' => array_map(fn (CouponQuoteLine $line): array => [
                'variant_id' => $line->variantId,
                'quantity' => $line->quantity,
                'unit_price' => $line->unitPrice,
                'total' => $line->total,
                'eligible' => $line->isEligible(),
                'reason' => $line->reason?->value,
            ], $quote->lines),
            'subtotal' => $quote->subtotal,
            'eligible_subtotal' => $quote->eligibleSubtotal,
            'uncapped_discount' => $quote->uncappedDiscount,
            'discount' => $quote->discount,
            'free_shipping' => $quote->freeShipping,
            'problem' => $quote->problem?->value,
            'total' => $quote->total(),
        ];
    }
}

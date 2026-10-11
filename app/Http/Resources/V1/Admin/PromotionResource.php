<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Admin;

use App\Models\Promotion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A promotion row with its variant and product, as the promotions page shows it. Amounts are Rials.
 *
 * @mixin Promotion
 */
class PromotionResource extends JsonResource
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
            'discount_type' => $this->discount_type->value,
            'discount_value' => $this->discount_value,
            'max_per_order' => $this->max_per_order,
            'quantity_limit' => $this->quantity_limit,
            'sold_count' => $this->sold_count,
            'starts_at' => $this->starts_at->toIso8601String(),
            'ends_at' => $this->ends_at->toIso8601String(),
            'state' => $this->state(),
            ...SaleEventVariantResource::describe($this->variant, $this->product),
        ];
    }

    /**
     * The relations toArray() reads.
     *
     * @return list<string>
     */
    public static function relations(): array
    {
        return ['variant.optionValues', 'product.options', 'product.galleryAssets.media'];
    }
}

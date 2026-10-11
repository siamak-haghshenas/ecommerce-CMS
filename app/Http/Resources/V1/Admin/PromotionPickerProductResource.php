<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Admin;

use App\Models\MediaAsset;
use App\Models\OptionValue;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Promotion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A product in the promotions page's "Add product" dialog, with its enabled variants (only the
 * ones with something to sell when the request filters on in stock). Amounts are Rials.
 *
 * @mixin Product
 */
class PromotionPickerProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $inStockOnly = $request->query('stock') === 'in';
        $optionOrder = $this->options->pluck('id')->flip();
        /** @var MediaAsset|null $image */
        $image = $this->galleryAssets->first();
        $now = now();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'image' => $image?->urls()['thumb'] ?? null,
            'variants' => $this->variants
                ->filter(fn (ProductVariant $variant): bool => $variant->is_enabled && (! $inStockOnly || $this->resource->isAvailable($variant)))
                ->map(fn (ProductVariant $variant): array => [
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'label' => $variant->optionValues
                        ->sortBy(fn (OptionValue $value): int => $optionOrder[$value->option_id] ?? PHP_INT_MAX)
                        ->pluck('value')
                        ->implode(' · '),
                    'price' => $variant->price,
                    'current_price' => $variant->currentPrice(),
                    'in_stock' => $this->resource->isAvailable($variant),
                    // Null when the product doesn't track stock.
                    'stock_quantity' => $this->track_stock ? $variant->stock_quantity : null,
                    // A row that hasn't ended: a new one must not overlap it.
                    'has_promotion' => $variant->promotions->contains(fn (Promotion $promotion): bool => $promotion->ends_at->gt($now)),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * The relations toArray() reads.
     *
     * @return list<string>
     */
    public static function relations(): array
    {
        return ['options', 'galleryAssets.media', 'variants.optionValues', 'variants.promotions'];
    }
}

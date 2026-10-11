<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Admin;

use App\Models\MediaAsset;
use App\Models\OptionValue;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A variant as the sale event editor shows it, with its product: what an event item points at, and
 * what "Add products" adds. Amounts are Rials.
 *
 * @mixin ProductVariant
 */
class SaleEventVariantResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return self::describe($this->resource, $this->resource->product);
    }

    /**
     * @return array{variant: array<string, mixed>, product: array<string, mixed>}
     */
    public static function describe(ProductVariant $variant, Product $product): array
    {
        // Variant labels list the values in the product's option order ("M · Red").
        $optionOrder = $product->options->pluck('id')->flip();
        /** @var MediaAsset|null $image */
        $image = $product->galleryAssets->first();

        return [
            'variant' => [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'label' => $variant->optionValues
                    ->sortBy(fn (OptionValue $value): int => $optionOrder[$value->option_id] ?? PHP_INT_MAX)
                    ->pluck('value')
                    ->implode(' · '),
                'price' => $variant->price,
                'sale_price' => $variant->sale_price,
                'current_price' => $variant->currentPrice(),
                'discount_source' => $variant->discount_source?->value,
                'sale_event_id' => $variant->sale_event_id,
                'promotion_id' => $variant->promotion_id,
                'is_enabled' => $variant->is_enabled,
                'is_deleted' => $variant->trashed(),
            ],
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'type' => $product->type->value,
                'image' => $image?->urls()['thumb'] ?? null,
                'is_deleted' => $product->trashed(),
            ],
        ];
    }

    /**
     * The relations describe() reads, from a variant.
     *
     * @return list<string>
     */
    public static function relations(): array
    {
        return ['optionValues', 'product.options', 'product.galleryAssets.media'];
    }
}

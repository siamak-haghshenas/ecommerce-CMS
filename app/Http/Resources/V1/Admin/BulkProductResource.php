<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Admin;

use App\Models\OptionValue;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A product on the bulk edit page with every variant's editable values. Amounts are Rials.
 *
 * @mixin Product
 */
class BulkProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $gallery = $this->getMedia(Product::GALLERY_COLLECTION)->keyBy('id');
        $thumb = fn (?Media $media): ?string => $media?->getAvailableUrl(['thumb']) ?: null;
        // Variant labels list the values in the product's option order ("M · Red").
        $optionOrder = $this->options->pluck('id')->flip();

        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'name' => $this->name,
            'status' => $this->status->value,
            'image' => $thumb($gallery->first()),
            'track_stock' => $this->track_stock,
            'low_stock_threshold' => $this->lowStockThreshold(),
            'variants' => $this->variants->map(fn (ProductVariant $variant): array => [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'label' => $variant->optionValues
                    ->sortBy(fn (OptionValue $value): int => $optionOrder[$value->option_id] ?? PHP_INT_MAX)
                    ->pluck('value')
                    ->implode(' · '),
                'image' => $variant->image_media_id === null ? null : $thumb($gallery->get($variant->image_media_id)),
                'price' => $variant->price,
                'sale_price' => $variant->sale_price,
                'sale_ends_at' => $variant->sale_price === null ? null : $variant->sale_ends_at?->toIso8601String(),
                'stock_quantity' => $variant->stock_quantity,
                'is_enabled' => $variant->is_enabled,
            ])->all(),
        ];
    }

    /**
     * The relations toArray() reads.
     *
     * @return list<string>
     */
    public static function relations(): array
    {
        return ['media', 'options', 'variants.optionValues'];
    }
}

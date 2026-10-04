<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Admin;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A row of the admin product list. Prices are whole Rials.
 *
 * @mixin Product
 */
class ProductListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $enabled = $this->variants->where('is_enabled', true);
        $prices = $enabled->map(fn (ProductVariant $variant): int => $variant->currentPrice());
        $primary = $this->primaryCategory();
        $threshold = $this->lowStockThreshold();

        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'name' => $this->name,
            'slug' => $this->slug,
            'status' => $this->status->value,
            'published_at' => $this->published_at?->toIso8601String(),
            'image' => $this->galleryAssets->first()?->urls()['thumb'] ?? null,
            'brand' => $this->brand === null ? null : ['id' => $this->brand->id, 'name' => $this->brand->name],
            'primary_category' => $primary === null ? null : ['id' => $primary->id, 'name' => $primary->name],
            'price' => $prices->isEmpty() ? null : ['min' => $prices->min(), 'max' => $prices->max()],
            'on_sale' => $enabled->contains(fn (ProductVariant $variant): bool => $variant->isOnSale()),
            'stock' => ! $this->track_stock ? null : [
                'total' => (int) $enabled->sum('stock_quantity'),
                'low' => $enabled->contains(fn (ProductVariant $variant): bool => $variant->stock_quantity <= $threshold),
                'out' => ! $this->allow_backorders && ! $enabled->contains(fn (ProductVariant $variant): bool => $variant->stock_quantity > 0),
            ],
            'variants_count' => $this->variants->count(),
            'seo_score' => $this->seo_score,
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}

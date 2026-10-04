<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Storefront;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\RichText;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A product card for storefront lists. Amounts are Rials; `price` is what shoppers pay now
 * (sale-aware), `regular_price` before any sale. Stock quantities are never exposed.
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
        $variants = $this->variants->where('is_enabled', true);
        $current = $variants->map(fn (ProductVariant $variant): int => $variant->currentPrice());
        $regular = $variants->map(fn (ProductVariant $variant): int => $variant->price);
        $image = $this->galleryAssets->first()?->picture();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            // Sanitized HTML (bold, italic, links, lists), and the same as plain text for cards and meta tags.
            'short_description' => $this->short_description,
            'short_description_text' => $this->short_description === null ? null : RichText::toPlainText($this->short_description),
            'image' => $image === null ? null : ['thumb' => $image['thumb'], 'medium' => $image['medium'], 'alt' => $image['alt']],
            'brand' => $this->brand?->is_active ? ['name' => $this->brand->name, 'persian_name' => $this->brand->persian_name, 'slug' => $this->brand->slug] : null,
            'price' => ['min' => $current->min(), 'max' => $current->max()],
            'regular_price' => ['min' => $regular->min(), 'max' => $regular->max()],
            'discount_percent' => (int) $variants->max(fn (ProductVariant $variant): int => $variant->discountPercent()),
            'in_stock' => $variants->contains(fn (ProductVariant $variant): bool => $this->resource->isAvailable($variant)),
            'has_variants' => $variants->count() > 1,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Admin;

use App\Models\Category;
use App\Models\Option;
use App\Models\OptionValue;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Everything the product editor needs. Prices are whole Rials; send `stock_quantity` back as
 * `stock_quantity_was` when changing stock, so concurrent changes are detected.
 *
 * @mixin Product
 */
class ProductResource extends JsonResource
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
            'type' => $this->type->value,
            'name' => $this->name,
            'slug' => $this->slug,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'status' => $this->status->value,
            'published_at' => $this->published_at?->toIso8601String(),
            'brand' => $this->brand === null ? null : ['id' => $this->brand->id, 'name' => $this->brand->name],
            'categories' => $this->categories->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'is_primary' => (bool) $category->getRelationValue('pivot')?->getAttribute('is_primary'),
            ])->values()->all(),
            'tags' => $this->tags->map(fn (Tag $tag): array => ['id' => $tag->id, 'name' => $tag->name, 'slug' => $tag->slug])->all(),
            'track_stock' => $this->track_stock,
            'allow_backorders' => $this->allow_backorders,
            'low_stock_threshold' => $this->low_stock_threshold,
            'low_stock_threshold_effective' => $this->lowStockThreshold(),
            'weight_grams' => $this->weight_grams,
            'length_mm' => $this->length_mm,
            'width_mm' => $this->width_mm,
            'height_mm' => $this->height_mm,
            // With all their values, for the variant grid.
            'options' => $this->options->map(fn (Option $option): array => [
                'id' => $option->id,
                'name' => $option->name,
                'slug' => $option->slug,
                'display_type' => $option->display_type->value,
                'values' => $option->values->map(fn (OptionValue $value): array => [
                    'id' => $value->id, 'value' => $value->value, 'slug' => $value->slug, 'color_hex' => $value->color_hex,
                ])->all(),
            ])->all(),
            'variants' => $this->variants->map(fn (ProductVariant $variant): array => [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'price' => $variant->price,
                'sale_price' => $variant->sale_price,
                'sale_starts_at' => $variant->sale_starts_at?->toIso8601String(),
                'sale_ends_at' => $variant->sale_ends_at?->toIso8601String(),
                'current_price' => $variant->currentPrice(),
                'discount_percent' => $variant->discountPercent(),
                'stock_quantity' => $variant->stock_quantity,
                'is_enabled' => $variant->is_enabled,
                'image_id' => $variant->image_asset_id,
                'option_value_ids' => $variant->optionValues->pluck('id')->sort()->values()->all(),
            ])->all(),
            'specifications' => $this->attributeValues
                ->groupBy('attribute_id')
                ->map(fn ($rows, int $attributeId): array => [
                    'attribute_id' => $attributeId,
                    'value_ids' => $rows->pluck('attribute_value_id')->filter()->values()->all(),
                    'text' => $rows->first()?->value_text,
                    'number' => $rows->first()?->value_number,
                    'boolean' => $rows->first()?->value_boolean,
                ])
                ->values()
                ->all(),
            'images' => $this->images(),
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'focus_keyphrase' => $this->focus_keyphrase,
            'seo_score' => $this->seo_score,
            'readability_score' => $this->readability_score,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }

    /**
     * The relations toArray() reads.
     *
     * @return list<string>
     */
    public static function relations(): array
    {
        return ['brand', 'categories', 'tags', 'options.values', 'variants.optionValues', 'attributeValues', 'galleryAssets.media'];
    }
}

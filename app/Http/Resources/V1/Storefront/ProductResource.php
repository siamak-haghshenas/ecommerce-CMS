<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Storefront;

use App\Data\Categories\CategoryNode;
use App\Enums\AttributeType;
use App\Enums\DiscountSource;
use App\Models\Category;
use App\Models\OptionValue;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductVariant;
use App\Support\CategoryTree;
use App\Support\RichText;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Everything a storefront needs for a product page. Only enabled variants, visible categories
 * and an active brand are included. Amounts are Rials; stock quantities are never exposed.
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
        $tree = CategoryTree::current();
        $variants = $this->variants->where('is_enabled', true)->values();
        $usedValueIds = $variants->flatMap(fn (ProductVariant $variant) => $variant->optionValues->pluck('id'))->unique()->all();
        $categories = $this->categories->filter(fn (Category $category): bool => $tree->isVisible($category->id))->values();
        $primary = $categories->first(fn (Category $category): bool => (bool) $category->getRelationValue('pivot')?->getAttribute('is_primary')) ?? $categories->first();
        $summary = fn (CategoryNode|Category $category): array => ['id' => $category->id, 'name' => $category->name, 'slug' => $category->slug];
        $current = $variants->map(fn (ProductVariant $variant): int => $variant->currentPrice());

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            // Sanitized HTML (bold, italic, links, lists), and the same as plain text for cards and meta tags.
            'short_description' => $this->short_description,
            'short_description_text' => $this->short_description === null ? null : RichText::toPlainText($this->short_description),
            // Cleaned on save: safe to render as HTML.
            'description' => $this->description,
            'brand' => $this->brand?->is_active ? [
                'name' => $this->brand->name, 'persian_name' => $this->brand->persian_name,
                'slug' => $this->brand->slug, 'logo' => $this->brand->logoAsset()?->picture(),
            ] : null,
            'categories' => $categories->map(fn (Category $category): array => [...$summary($category), 'is_primary' => $category->is($primary)])->all(),
            // Ancestors and the primary category itself, top first.
            'breadcrumb' => $primary === null ? [] : [...array_map($summary, $tree->ancestors($primary->id)), $summary($primary)],
            'tags' => $this->tags->map(fn ($tag): array => ['name' => $tag->name, 'slug' => $tag->slug])->all(),
            'images' => $this->images(),
            'price' => ['min' => $current->min(), 'max' => $current->max()],
            'in_stock' => $variants->contains(fn (ProductVariant $variant): bool => $this->resource->isAvailable($variant)),
            // Only the values the enabled variants use, so storefronts never offer a dead choice.
            'options' => $this->options->map(fn ($option): array => [
                'id' => $option->id,
                'name' => $option->name,
                'slug' => $option->slug,
                'display_type' => $option->display_type->value,
                'values' => $option->values
                    ->filter(fn (OptionValue $value): bool => in_array($value->id, $usedValueIds, true))
                    ->map(fn (OptionValue $value): array => ['id' => $value->id, 'value' => $value->value, 'slug' => $value->slug, 'color_hex' => $value->color_hex])
                    ->values()
                    ->all(),
            ])->all(),
            'variants' => $variants->map(fn (ProductVariant $variant): array => [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'option_value_ids' => $variant->optionValues->pluck('id')->sort()->values()->all(),
                'price' => $variant->currentPrice(),
                'regular_price' => $variant->price,
                'discount_percent' => $variant->discountPercent(),
                // What gives the price: the variant's own sale, a sale event or a promotion (null: the regular price).
                'discount_source' => $variant->discount_source?->value,
                'sale_ends_at' => $variant->discount_source === DiscountSource::Sale ? $variant->sale_ends_at?->toIso8601String() : null,
                'sale_event' => $this->saleEvent($variant),
                // When a promotion gives the price: when it ends and how many discounted units are left (null: no limit).
                'promotion' => $variant->discount_source === DiscountSource::Promotion && $variant->promotion !== null
                    ? ['ends_at' => $variant->promotion->ends_at->toIso8601String(), 'remaining' => $variant->promotion->remaining()]
                    : null,
                // The most units one order (cart) may hold at this price, set by the sale event or promotion giving it; null: no limit.
                'max_per_order' => $variant->max_per_order,
                'in_stock' => $this->resource->isAvailable($variant),
                'low_stock' => $this->resource->isLowStock($variant),
                'image_id' => $variant->image_asset_id,
            ])->all(),
            'specifications' => $this->specifications(),
            'shipping' => [
                'weight_grams' => $this->weight_grams,
                'length_mm' => $this->length_mm,
                'width_mm' => $this->width_mm,
                'height_mm' => $this->height_mm,
            ],
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'published_at' => $this->published_at?->toIso8601String(),
        ];
    }

    /**
     * The specifications table: one row per attribute, values as display text.
     *
     * @return list<array{name: string, slug: string, type: string, unit: string|null, values: list<string>}>
     */
    private function specifications(): array
    {
        return $this->attributeValues
            ->groupBy('attribute_id')
            ->map(function ($rows): array {
                /** @var ProductAttributeValue $first */
                $first = $rows->first();
                $attribute = $first->attribute;

                $values = match ($attribute->type) {
                    AttributeType::Select => $rows->map(fn (ProductAttributeValue $row): string => (string) $row->attributeValue?->value)->filter()->values()->all(),
                    AttributeType::Number => [rtrim(rtrim(number_format((float) $first->value_number, 3, '.', ''), '0'), '.')],
                    AttributeType::Boolean => [$first->value_boolean ? 'yes' : 'no'],
                    AttributeType::Text => [(string) $first->value_text],
                };

                return ['name' => $attribute->name, 'slug' => $attribute->slug, 'type' => $attribute->type->value, 'unit' => $attribute->unit, 'values' => $values];
            })
            ->values()
            ->all();
    }

    /**
     * The sale event giving the variant's price: its name, the landing page's slug (null without a
     * visible landing page) and when the discount ends for this variant (the item's own end or the event's).
     *
     * @return array{name: string, slug: string|null, ends_at: string}|null
     */
    private function saleEvent(ProductVariant $variant): ?array
    {
        $event = $variant->saleEvent;

        if ($variant->discount_source !== DiscountSource::SaleEvent || $event === null) {
            return null;
        }

        $item = $variant->saleEventItems->firstWhere('sale_event_id', $event->id);
        $endsAt = $item === null ? $event->ends_at : $item->ends_at ?? $event->ends_at;

        return [
            'name' => $event->name,
            'slug' => $event->hasVisibleLanding() ? $event->slug : null,
            'ends_at' => $endsAt->toIso8601String(),
        ];
    }

    /**
     * The relations toArray() reads.
     *
     * @return list<string>
     */
    public static function relations(): array
    {
        return [
            'brand.logoAssets.media', 'categories', 'tags', 'galleryAssets.media', 'options.values',
            'variants.optionValues', 'variants.saleEvent', 'variants.promotion', 'variants.saleEventItems',
            'attributeValues.attribute', 'attributeValues.attributeValue',
        ];
    }
}

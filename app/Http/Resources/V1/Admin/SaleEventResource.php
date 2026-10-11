<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Admin;

use App\Models\SaleEvent;
use App\Models\SaleEventItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A sale event for the admin. The list has counts; the detail view (withItems()) adds every
 * variant with its product, current price and the higher-priority events that override it, plus
 * the landing page's content and SEO fields.
 *
 * @mixin SaleEvent
 */
class SaleEventResource extends JsonResource
{
    /** @var array<int, list<array{id: int, name: string, starts_at: string, ends_at: string}>>|null by variant id; null: the list view */
    public ?array $overriding = null;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'starts_at' => $this->starts_at->toIso8601String(),
            'ends_at' => $this->ends_at->toIso8601String(),
            'is_active' => $this->is_active,
            'state' => $this->state(),
            'position' => $this->position,
            'has_landing_page' => $this->has_landing_page,
            'slug' => $this->slug,
            'items_count' => $this->whenCounted('items'),
            'products_count' => $this->when($this->resource->getAttribute('products_count') !== null, fn (): int => (int) $this->resource->getAttribute('products_count')),
            'seo_score' => $this->seo_score,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        if ($this->overriding === null) {
            return $data;
        }

        return [
            ...$data,
            'items' => $this->items->map(fn (SaleEventItem $item): array => $this->item($item))->all(),
            'landing_title' => $this->landing_title,
            'landing_description' => $this->landing_description,
            'banner' => $this->bannerAsset()?->picture(),
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'focus_keyphrase' => $this->focus_keyphrase,
            'readability_score' => $this->readability_score,
            'robots_noindex' => $this->robots_noindex,
            'canonical_url' => $this->canonical_url,
            'og_title' => $this->og_title,
            'og_description' => $this->og_description,
            'og_image' => $this->ogImageAsset()?->picture(),
        ];
    }

    /**
     * The detail view, with the variants that higher-priority events override (by variant id).
     *
     * @param  array<int, list<array{id: int, name: string, starts_at: string, ends_at: string}>>  $overriding
     */
    public function withItems(array $overriding): self
    {
        $this->overriding = $overriding;

        return $this;
    }

    /**
     * The relations the detail view reads.
     *
     * @return list<string>
     */
    public static function detailRelations(): array
    {
        return [
            'items.variant.optionValues', 'items.product.options', 'items.product.galleryAssets.media',
            'bannerAssets.media', 'ogImageAssets.media',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function item(SaleEventItem $item): array
    {
        return [
            'id' => $item->id,
            'discount_type' => $item->discount_type->value,
            'discount_value' => $item->discount_value,
            'max_per_order' => $item->max_per_order,
            'starts_at' => $item->starts_at?->toIso8601String(),
            'ends_at' => $item->ends_at?->toIso8601String(),
            'overridden_by' => $this->overriding[$item->product_variant_id] ?? [],
            ...SaleEventVariantResource::describe($item->variant, $item->product),
        ];
    }
}

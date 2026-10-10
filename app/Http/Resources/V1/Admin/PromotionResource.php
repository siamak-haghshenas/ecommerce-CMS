<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Admin;

use App\Enums\PromotionTargetType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\PromotionTarget;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A promotion for the admin. Targets are listed with their names; `products_count` (how many
 * products it applies to) is only included on the detail view.
 *
 * @mixin Promotion
 */
class PromotionResource extends JsonResource
{
    public ?int $productsCount = null;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $targets = $this->targets;
        $ids = fn (PromotionTargetType $type, bool $excluded = false): array => $targets
            ->filter(fn (PromotionTarget $target): bool => $target->target_type === $type && $target->is_excluded === $excluded)
            ->pluck('target_id')
            ->all();
        $named = fn (string $model, array $ids, string $column = 'name'): array => $ids === [] ? [] : $model::query()
            ->whereKey($ids)
            ->orderBy($column)
            ->get(['id', $column])
            ->map(fn ($item): array => ['id' => $item->id, 'name' => $item->{$column}])
            ->all();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type->value,
            'value' => $this->value,
            'max_discount' => $this->max_discount,
            'scope' => $this->scope->value,
            'state' => $this->state(),
            'products' => $named(Product::class, $ids(PromotionTargetType::Product)),
            'categories' => $named(Category::class, $ids(PromotionTargetType::Category)),
            'brands' => $named(Brand::class, $ids(PromotionTargetType::Brand)),
            'tags' => $named(Tag::class, $ids(PromotionTargetType::Tag)),
            'excluded_products' => $named(Product::class, $ids(PromotionTargetType::Product, true)),
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'is_active' => $this->is_active,
            'products_count' => $this->when($this->productsCount !== null, $this->productsCount),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    public function withProductsCount(int $count): self
    {
        $this->productsCount = $count;

        return $this;
    }
}

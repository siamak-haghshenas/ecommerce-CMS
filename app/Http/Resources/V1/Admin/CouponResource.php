<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Admin;

use App\Models\Coupon;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * A coupon for the admin. The list has counts; the detail view (detailRelations() loaded) adds
 * its eligible items, excluded products and the customers it is limited to. Amounts are Rials.
 *
 * @mixin Coupon
 */
class CouponResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type->value,
            'value' => $this->value,
            'max_discount' => $this->max_discount,
            'min_order_amount' => $this->min_order_amount,
            'applies_to' => $this->applies_to->value,
            'targets_count' => [
                'categories' => $this->countOf('categories'),
                'brands' => $this->countOf('brands'),
                'tags' => $this->countOf('tags'),
                'products' => $this->countOf('products'),
            ],
            'excluded_count' => $this->countOf('excludedProducts'),
            'exclude_discounted' => $this->exclude_discounted,
            'customer_scope' => $this->customer_scope->value,
            'customers_count' => $this->countOf('customers'),
            'usage_limit' => $this->usage_limit,
            'per_customer_limit' => $this->per_customer_limit,
            'used_count' => $this->used_count,
            'first_order_only' => $this->first_order_only,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'is_active' => $this->is_active,
            'state' => $this->state(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        if (! $this->relationLoaded('customers')) {
            return $data;
        }

        return [
            ...$data,
            'targets' => [
                'categories' => $this->links($this->categories),
                'brands' => $this->links($this->brands),
                'tags' => $this->links($this->tags),
                'products' => $this->links($this->products),
            ],
            'excluded_products' => $this->links($this->excludedProducts),
            'customers' => CouponCustomerResource::collection($this->customers),
        ];
    }

    /**
     * The relations the detail view shows.
     *
     * @return array<string, mixed>
     */
    public static function detailRelations(): array
    {
        $named = fn (string $table): Closure => fn ($query) => $query->select(["{$table}.id", "{$table}.name"])->orderBy("{$table}.name");

        return [
            'categories' => $named('categories'),
            'brands' => $named('brands'),
            'tags' => $named('tags'),
            'products' => $named('products'),
            'excludedProducts' => $named('products'),
            'customers' => fn ($query) => $query->select(['users.id', 'users.name', 'users.phone_number'])->orderBy('users.name'),
        ];
    }

    /**
     * From withCount() in the list, else the loaded relation.
     */
    private function countOf(string $relation): int
    {
        $counted = $this->resource->getAttribute(Str::snake($relation).'_count');

        if ($counted !== null) {
            return (int) $counted;
        }

        return $this->resource->relationLoaded($relation) ? $this->resource->getRelation($relation)->count() : 0;
    }

    /**
     * @template TModel of Model
     *
     * @param  Collection<int, TModel>  $items
     * @return list<array{id: int, name: string}>
     */
    private function links(Collection $items): array
    {
        return $items->map(fn (Model $item): array => ['id' => (int) $item->getKey(), 'name' => (string) $item->getAttribute('name')])->values()->all();
    }
}

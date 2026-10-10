<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Data\Catalog\StorefrontProductFilters;
use App\Enums\AttributeType;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Option;
use App\Models\OptionValue;
use App\Support\Catalog\StorefrontProductQuery;
use App\Support\CategoryAttributes;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The filters a storefront can offer for a set of products (search, category, brand, tag),
 * with product counts. Counts ignore the refining filters, so every option stays visible.
 */
final class BuildStorefrontFiltersAction
{
    /**
     * @return array{
     *     total: int,
     *     price: array{min: int, max: int}|null,
     *     brands: list<array{id: int, name: string, persian_name: string|null, slug: string, count: int}>,
     *     attributes: list<array<string, mixed>>,
     *     options: list<array<string, mixed>>,
     * }
     */
    public function handle(StorefrontProductFilters $filters): array
    {
        $base = StorefrontProductQuery::applyScope(StorefrontProductQuery::visible(), $filters)->select('products.id');
        $productIds = $base->pluck('products.id')->all();

        if ($productIds === []) {
            return ['total' => 0, 'price' => null, 'brands' => [], 'attributes' => [], 'options' => []];
        }

        return [
            'total' => count($productIds),
            'price' => $this->priceRange($productIds),
            'brands' => $this->brands($productIds),
            'attributes' => $this->attributes($productIds, $filters->categoryId),
            'options' => $this->options($productIds),
        ];
    }

    /**
     * @param  list<int>  $productIds
     * @return array{min: int, max: int}|null
     */
    private function priceRange(array $productIds): ?array
    {
        $range = DB::table('product_variants')
            ->selectRaw('MIN('.StorefrontProductQuery::CURRENT_PRICE.') AS min_price')
            ->selectRaw('MAX('.StorefrontProductQuery::CURRENT_PRICE.') AS max_price')
            ->whereIn('product_id', $productIds)
            ->where('is_enabled', true)
            ->whereNull('deleted_at')
            ->first();

        return $range?->min_price === null ? null : ['min' => (int) $range->min_price, 'max' => (int) $range->max_price];
    }

    /**
     * @param  list<int>  $productIds
     * @return list<array{id: int, name: string, persian_name: string|null, slug: string, count: int}>
     */
    private function brands(array $productIds): array
    {
        $counts = DB::table('products')->whereIn('id', $productIds)->whereNotNull('brand_id')
            ->groupBy('brand_id')->selectRaw('brand_id, COUNT(*) AS products')->pluck('products', 'brand_id');

        return Brand::query()->whereKey($counts->keys()->all())->where('is_active', true)->ordered()->get()
            ->map(fn (Brand $brand): array => [
                'id' => $brand->id,
                'name' => $brand->name,
                'persian_name' => $brand->persian_name,
                'slug' => $brand->slug,
                'count' => (int) $counts[$brand->id],
            ])
            ->values()
            ->all();
    }

    /**
     * Filterable specifications: those of the category (and its ancestors) on a category page,
     * every filterable one otherwise. Only values products actually have are listed.
     *
     * @param  list<int>  $productIds
     * @return list<array<string, mixed>>
     */
    private function attributes(array $productIds, ?int $categoryId): array
    {
        /** @var Collection<int, Attribute> $attributes */
        $attributes = $categoryId !== null
            ? CategoryAttributes::applicable([$categoryId])->where('is_filterable', true)->values()
            : Attribute::query()->where('is_filterable', true)->with('values')->ordered()->get();

        $rows = DB::table('product_attribute_values')
            ->whereIn('product_id', $productIds)
            ->whereIn('attribute_id', $attributes->pluck('id')->all() ?: [0]);

        $valueCounts = (clone $rows)->whereNotNull('attribute_value_id')->groupBy('attribute_value_id')
            ->selectRaw('attribute_value_id, COUNT(DISTINCT product_id) AS products')->pluck('products', 'attribute_value_id');
        $numbers = (clone $rows)->whereNotNull('value_number')->groupBy('attribute_id')
            ->selectRaw('attribute_id, MIN(value_number) AS min_value, MAX(value_number) AS max_value')->get()->keyBy('attribute_id');
        $booleans = (clone $rows)->whereNotNull('value_boolean')->groupBy('attribute_id', 'value_boolean')
            ->selectRaw('attribute_id, value_boolean, COUNT(DISTINCT product_id) AS products')->get()->groupBy('attribute_id');

        return $attributes->map(function (Attribute $attribute) use ($valueCounts, $numbers, $booleans): ?array {
            $common = ['id' => $attribute->id, 'name' => $attribute->name, 'slug' => $attribute->slug, 'type' => $attribute->type->value, 'unit' => $attribute->unit];

            return match ($attribute->type) {
                AttributeType::Select => ($values = $attribute->values
                    ->filter(fn (AttributeValue $value): bool => isset($valueCounts[$value->id]))
                    ->map(fn (AttributeValue $value): array => ['id' => $value->id, 'value' => $value->value, 'slug' => $value->slug, 'count' => (int) $valueCounts[$value->id]])
                    ->values()->all()) === [] ? null : [...$common, 'values' => $values],
                AttributeType::Number => isset($numbers[$attribute->id])
                    ? [...$common, 'min' => (float) $numbers[$attribute->id]->min_value, 'max' => (float) $numbers[$attribute->id]->max_value]
                    : null,
                AttributeType::Boolean => isset($booleans[$attribute->id])
                    ? [...$common, 'counts' => $booleans[$attribute->id]->mapWithKeys(fn ($row): array => [((bool) $row->value_boolean) ? 'yes' : 'no' => (int) $row->products])->all()]
                    : null,
                // Free text can't be filtered by choice.
                AttributeType::Text => null,
            };
        })->filter()->values()->all();
    }

    /**
     * Option values used by enabled variants of these products.
     *
     * @param  list<int>  $productIds
     * @return list<array<string, mixed>>
     */
    private function options(array $productIds): array
    {
        $counts = DB::table('option_value_product_variant')
            ->join('product_variants', 'product_variants.id', '=', 'option_value_product_variant.product_variant_id')
            ->whereIn('product_variants.product_id', $productIds)
            ->where('product_variants.is_enabled', true)
            ->whereNull('product_variants.deleted_at')
            ->groupBy('option_value_id')
            ->selectRaw('option_value_id, COUNT(DISTINCT product_variants.product_id) AS products')
            ->pluck('products', 'option_value_id');

        if ($counts->isEmpty()) {
            return [];
        }

        return Option::query()
            ->whereHas('values', fn ($values) => $values->whereKey($counts->keys()->all()))
            ->with('values')
            ->ordered()
            ->get()
            ->map(fn (Option $option): array => [
                'id' => $option->id,
                'name' => $option->name,
                'slug' => $option->slug,
                'display_type' => $option->display_type->value,
                'values' => $option->values
                    ->filter(fn (OptionValue $value): bool => isset($counts[$value->id]))
                    ->map(fn (OptionValue $value): array => [
                        'id' => $value->id, 'value' => $value->value, 'slug' => $value->slug,
                        'color_hex' => $value->color_hex, 'count' => (int) $counts[$value->id],
                    ])
                    ->values()
                    ->all(),
            ])
            ->all();
    }
}

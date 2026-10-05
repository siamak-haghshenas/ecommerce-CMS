<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Storefront\Catalog;

use App\Data\Catalog\StorefrontProductFilters;
use App\Enums\AttributeType;
use App\Enums\CategoryType;
use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Option;
use App\Models\Tag;
use App\Support\CategoryTree;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Query parameters of the public product list and its filters, in storefront URL form:
 *
 *   ?q=کفش&category=shoes&brand=adidas&tag=gift
 *   &filter[material]=cotton,linen   ("select": any of the value slugs)
 *   &filter[screen-size]=13..16      ("number": a range; either side may be empty)
 *   &filter[waterproof]=1            ("boolean")
 *   &option[color]=red,blue&option[size]=xl   (one variant must match every option)
 *   &min_price=…&max_price=…         (Rials)  &in_stock=1&on_sale=1&sort=price_asc&per_page=24
 *
 * Unknown slugs match nothing (a stale storefront link shows an empty list, not an error).
 */
class ProductFiltersRequest extends FormRequest
{
    /** Matches nothing: an id no row has. */
    private const int NONE = 0;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'tag' => ['nullable', 'string', 'max:255'],
            'filter' => ['nullable', 'array', 'max:20'],
            'filter.*' => ['string', 'max:500'],
            'option' => ['nullable', 'array', 'max:5'],
            'option.*' => ['string', 'max:500'],
            'min_price' => ['nullable', 'integer', 'min:0'],
            'max_price' => ['nullable', 'integer', 'min:0'],
            'in_stock' => ['nullable', 'boolean'],
            'on_sale' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(['newest', 'price_asc', 'price_desc', 'name'])],
            'per_page' => ['nullable', 'integer', 'between:1,48'],
        ];
    }

    public function toFilters(): StorefrontProductFilters
    {
        $category = $this->filled('category') ? CategoryTree::current()->findBySlug($this->string('category')->toString(), CategoryType::Product) : null;

        return new StorefrontProductFilters(
            search: $this->filled('q') ? $this->string('q')->toString() : null,
            categoryId: $this->filled('category') ? ($category->id ?? self::NONE) : null,
            brandId: $this->filled('brand')
                ? (Brand::query()->where('slug', $this->string('brand')->toString())->where('is_active', true)->value('id') ?? self::NONE)
                : null,
            tagId: $this->filled('tag') ? (Tag::query()->where('slug', $this->string('tag')->toString())->value('id') ?? self::NONE) : null,
            attributes: $this->attributeConditions(),
            options: $this->optionConditions(),
            minPrice: $this->filled('min_price') ? $this->integer('min_price') : null,
            maxPrice: $this->filled('max_price') ? $this->integer('max_price') : null,
            inStock: $this->boolean('in_stock'),
            onSale: $this->boolean('on_sale'),
            sort: $this->string('sort', 'newest')->toString(),
            perPage: $this->filled('per_page') ? $this->integer('per_page') : 24,
        );
    }

    /**
     * @return array<int, list<int>|array{min: float|null, max: float|null}|bool>
     */
    private function attributeConditions(): array
    {
        $requested = (array) $this->input('filter', []);

        if ($requested === []) {
            return [];
        }

        $attributes = Attribute::query()->whereIn('slug', array_keys($requested))->where('is_filterable', true)->with('values')->get();
        $conditions = [];

        foreach ($requested as $slug => $value) {
            $attribute = $attributes->firstWhere('slug', $slug);

            if ($attribute === null) {
                // An unknown or non-filterable specification matches nothing.
                $conditions[self::NONE] = [self::NONE];

                continue;
            }

            $conditions[$attribute->id] = match ($attribute->type) {
                AttributeType::Number => $this->range((string) $value),
                AttributeType::Boolean => in_array((string) $value, ['1', 'true', 'yes'], true),
                default => $attribute->values->whereIn('slug', $this->slugs((string) $value))->pluck('id')->values()->all() ?: [self::NONE],
            };
        }

        return $conditions;
    }

    /**
     * @return array<int, list<int>>
     */
    private function optionConditions(): array
    {
        $requested = (array) $this->input('option', []);

        if ($requested === []) {
            return [];
        }

        $options = Option::query()->whereIn('slug', array_keys($requested))->with('values')->get();
        $conditions = [];

        foreach ($requested as $slug => $value) {
            $option = $options->firstWhere('slug', $slug);
            $conditions[$option->id ?? self::NONE] = $option?->values->whereIn('slug', $this->slugs((string) $value))->pluck('id')->values()->all() ?: [self::NONE];
        }

        return $conditions;
    }

    /**
     * "13..16", "13.." or "..16".
     *
     * @return array{min: float|null, max: float|null}
     */
    private function range(string $value): array
    {
        [$min, $max] = array_pad(explode('..', $value, 2), 2, '');

        return [
            'min' => is_numeric($min) ? (float) $min : null,
            'max' => is_numeric($max) ? (float) $max : null,
        ];
    }

    /**
     * @return list<string>
     */
    private function slugs(string $value): array
    {
        return array_values(array_filter(array_map('trim', explode(',', mb_strtolower($value)))));
    }
}

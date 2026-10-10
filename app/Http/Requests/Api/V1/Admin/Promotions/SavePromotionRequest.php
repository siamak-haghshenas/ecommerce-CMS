<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Promotions;

use App\Data\Promotions\PromotionData;
use App\Enums\CategoryType;
use App\Enums\PromotionScope;
use App\Enums\PromotionType;
use App\Models\Promotion;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Creating and updating a promotion share the same fields.
 */
class SavePromotionRequest extends FormRequest
{
    /** At most this many ids per target list. */
    private const int MAX_TARGETS = 500;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $ids = ['array', 'max:'.self::MAX_TARGETS];

        return [
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::enum(PromotionType::class)],
            'value' => ['required', 'integer', 'min:1', $this->input('type') === PromotionType::Percent->value ? 'max:'.Promotion::MAX_PERCENT : 'max:1000000000000'],
            'max_discount' => ['nullable', 'integer', 'min:1', 'max:1000000000000', Rule::prohibitedIf($this->input('type') !== PromotionType::Percent->value)],
            'scope' => ['required', Rule::enum(PromotionScope::class)],
            'product_ids' => $ids,
            'product_ids.*' => ['integer', 'distinct', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'category_ids' => $ids,
            'category_ids.*' => ['integer', 'distinct', Rule::exists('categories', 'id')->where('type', CategoryType::Product->value)],
            'brand_ids' => $ids,
            'brand_ids.*' => ['integer', 'distinct', Rule::exists('brands', 'id')],
            'tag_ids' => $ids,
            'tag_ids.*' => ['integer', 'distinct', Rule::exists('tags', 'id')],
            'excluded_product_ids' => $ids,
            'excluded_product_ids.*' => ['integer', 'distinct', Rule::exists('products', 'id')],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', ...($this->filled('starts_at') ? ['after:starts_at'] : [])],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $targets = [...$this->ints('product_ids'), ...$this->ints('category_ids'), ...$this->ints('brand_ids'), ...$this->ints('tag_ids')];

                // "Selected" needs something to apply to.
                if ($this->input('scope') === PromotionScope::Selected->value && $targets === []) {
                    $validator->errors()->add('scope', __('admin.promotions.errors.no_targets'));
                }
            },
        ];
    }

    public function toData(): PromotionData
    {
        $type = PromotionType::from($this->string('type')->toString());
        $scope = PromotionScope::from($this->string('scope')->toString());
        $date = fn (string $key): ?CarbonImmutable => $this->filled($key) ? CarbonImmutable::parse($this->string($key)->toString()) : null;

        return new PromotionData(
            name: $this->string('name')->trim()->toString(),
            type: $type,
            value: $this->integer('value'),
            maxDiscount: $type === PromotionType::Percent && $this->filled('max_discount') ? $this->integer('max_discount') : null,
            scope: $scope,
            // "All products" ignores the chosen targets; exclusions apply to both scopes.
            productIds: $scope === PromotionScope::Selected ? $this->ints('product_ids') : [],
            categoryIds: $scope === PromotionScope::Selected ? $this->ints('category_ids') : [],
            brandIds: $scope === PromotionScope::Selected ? $this->ints('brand_ids') : [],
            tagIds: $scope === PromotionScope::Selected ? $this->ints('tag_ids') : [],
            excludedProductIds: $this->ints('excluded_product_ids'),
            startsAt: $date('starts_at'),
            endsAt: $date('ends_at'),
            isActive: $this->boolean('is_active'),
        );
    }

    /**
     * @return list<int>
     */
    private function ints(string $key): array
    {
        return array_values(array_unique(array_map(intval(...), (array) $this->input($key, []))));
    }
}

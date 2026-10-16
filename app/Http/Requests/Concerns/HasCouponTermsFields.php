<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Data\Coupons\CouponTerms;
use App\Enums\CategoryType;
use App\Enums\CouponAppliesTo;
use App\Enums\CouponType;
use App\Models\Coupon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A coupon's terms (what it takes off and from which items), shared by the save and the sample
 * order, which sends them under `coupon.`: hence the field prefix.
 */
trait HasCouponTermsFields
{
    /** The largest amount (Rials) a field takes. */
    private const int MAX_AMOUNT = 1_000_000_000_000;

    /**
     * @return array<string, list<mixed>>
     */
    protected function couponTermsRules(string $prefix = ''): array
    {
        $amount = ['nullable', 'integer', 'min:1', 'max:'.self::MAX_AMOUNT];
        $list = ['sometimes', 'array', 'max:'.Coupon::MAX_TARGETS];

        return [
            $prefix.'type' => ['required', Rule::enum(CouponType::class)],
            $prefix.'value' => [Rule::requiredIf($this->input($prefix.'type') !== CouponType::FreeShipping->value), ...$amount],
            $prefix.'max_discount' => $amount,
            $prefix.'min_order_amount' => $amount,
            $prefix.'applies_to' => ['required', Rule::enum(CouponAppliesTo::class)],
            $prefix.'category_ids' => $list,
            $prefix.'category_ids.*' => ['integer', 'distinct', Rule::exists('categories', 'id')->where('type', CategoryType::Product->value)],
            $prefix.'brand_ids' => $list,
            $prefix.'brand_ids.*' => ['integer', 'distinct', Rule::exists('brands', 'id')],
            $prefix.'tag_ids' => $list,
            $prefix.'tag_ids.*' => ['integer', 'distinct', Rule::exists('tags', 'id')],
            $prefix.'product_ids' => $list,
            $prefix.'product_ids.*' => ['integer', 'distinct', Rule::exists('products', 'id')->whereNull('deleted_at')],
            $prefix.'excluded_product_ids' => $list,
            $prefix.'excluded_product_ids.*' => ['integer', 'distinct', Rule::exists('products', 'id')->whereNull('deleted_at')],
            $prefix.'exclude_discounted' => ['required', 'boolean'],
        ];
    }

    /**
     * Call from after(): a percentage of at most MAX_PERCENT, something chosen for `selected`, and
     * no product both chosen and excluded.
     */
    protected function checkCouponTerms(Validator $validator, string $prefix = ''): void
    {
        $terms = $this->couponTerms($prefix);

        if ($terms->type === CouponType::Percent && (int) $terms->value > Coupon::MAX_PERCENT) {
            $validator->errors()->add($prefix.'value', __('admin.coupons.errors.percent_max', ['max' => Coupon::MAX_PERCENT]));
        }

        if ($terms->appliesTo === CouponAppliesTo::Selected
            && [...$terms->categoryIds, ...$terms->brandIds, ...$terms->tagIds, ...$terms->productIds] === []) {
            $validator->errors()->add($prefix.'applies_to', __('admin.coupons.errors.no_targets'));
        }

        foreach ($terms->excludedProductIds as $i => $id) {
            if (in_array($id, $terms->productIds, true)) {
                $validator->errors()->add("{$prefix}excluded_product_ids.{$i}", __('admin.coupons.errors.excluded_and_chosen'));
            }
        }
    }

    /**
     * The validated terms. Fields the type doesn't use are null; target lists are empty for `all`.
     */
    protected function couponTerms(string $prefix = ''): CouponTerms
    {
        $type = CouponType::from((string) $this->input($prefix.'type'));
        $appliesTo = CouponAppliesTo::from((string) $this->input($prefix.'applies_to'));
        $optional = fn (string $key): ?int => $this->filled($prefix.$key) ? $this->integer($prefix.$key) : null;
        $ids = fn (string $key): array => $appliesTo === CouponAppliesTo::Selected || $key === 'excluded_product_ids'
            ? array_values(array_map(intval(...), (array) $this->input($prefix.$key, [])))
            : [];

        return new CouponTerms(
            type: $type,
            value: $type === CouponType::FreeShipping ? null : $optional('value'),
            maxDiscount: $type === CouponType::Percent ? $optional('max_discount') : null,
            minOrderAmount: $optional('min_order_amount'),
            appliesTo: $appliesTo,
            categoryIds: $ids('category_ids'),
            brandIds: $ids('brand_ids'),
            tagIds: $ids('tag_ids'),
            productIds: $ids('product_ids'),
            excludedProductIds: $ids('excluded_product_ids'),
            excludeDiscounted: $this->boolean($prefix.'exclude_discounted'),
        );
    }
}

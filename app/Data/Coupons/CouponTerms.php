<?php

declare(strict_types=1);

namespace App\Data\Coupons;

use App\Enums\CouponAppliesTo;
use App\Enums\CouponType;
use App\Models\Coupon;

/**
 * What a coupon takes off and from which items: everything the cart calculation needs, for a
 * saved coupon or the editor's unsaved one. Amounts are Rials; fields the type doesn't use are null.
 */
final readonly class CouponTerms
{
    /**
     * @param  list<int>  $categoryIds  with $productIds, $brandIds and $tagIds: for `selected` only
     * @param  list<int>  $brandIds
     * @param  list<int>  $tagIds
     * @param  list<int>  $productIds
     * @param  list<int>  $excludedProductIds  never eligible, whatever `applies_to` says
     */
    public function __construct(
        public CouponType $type,
        public ?int $value,
        public ?int $maxDiscount,
        public ?int $minOrderAmount,
        public CouponAppliesTo $appliesTo,
        public array $categoryIds,
        public array $brandIds,
        public array $tagIds,
        public array $productIds,
        public array $excludedProductIds,
        public bool $excludeDiscounted,
    ) {}

    /**
     * A saved coupon's terms (its target lists are read from `coupon_targets`).
     */
    public static function of(Coupon $coupon): self
    {
        $ids = fn (string $relation): array => array_map(intval(...), $coupon->{$relation}()->allRelatedIds()->all());

        return new self(
            type: $coupon->type,
            value: $coupon->value,
            maxDiscount: $coupon->max_discount,
            minOrderAmount: $coupon->min_order_amount,
            appliesTo: $coupon->applies_to,
            categoryIds: $ids('categories'),
            brandIds: $ids('brands'),
            tagIds: $ids('tags'),
            productIds: $ids('products'),
            excludedProductIds: $ids('excludedProducts'),
            excludeDiscounted: $coupon->exclude_discounted,
        );
    }
}

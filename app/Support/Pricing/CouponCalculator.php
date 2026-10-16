<?php

declare(strict_types=1);

namespace App\Support\Pricing;

use App\Data\Coupons\CouponItem;
use App\Data\Coupons\CouponQuote;
use App\Data\Coupons\CouponQuoteLine;
use App\Data\Coupons\CouponTerms;
use App\Enums\CouponAppliesTo;
use App\Enums\CouponLineReason;
use App\Enums\CouponProblem;
use App\Enums\CouponType;
use App\Models\ProductVariant;
use App\Support\CategoryTree;
use App\Support\Settings;

/**
 * Works a coupon out for a cart, for the admin's sample order, the storefront's code check and
 * later checkout. Lines are priced at the variant's current price (sales, sale events and
 * promotions already applied); the coupon comes off that, never stacked into the unit prices.
 *
 * A line is eligible unless its product is excluded, the coupon is for chosen items and it isn't
 * one of them (a chosen category covers its sub-categories), or it is discounted and the coupon
 * leaves discounted items out. The minimum order is compared with the eligible lines. A percent
 * discount is rounded down to whole Rials, or whole Toman when the store shows Toman, then capped
 * by `max_discount`; a fixed one never exceeds the eligible lines.
 */
final readonly class CouponCalculator
{
    public function __construct(private Settings $settings) {}

    /**
     * @param  list<CouponItem>  $items  variants that don't exist (or whose product is deleted) are left out
     */
    public function quote(CouponTerms $terms, array $items): CouponQuote
    {
        $variants = ProductVariant::query()
            ->with(['product' => fn ($query) => $query->select(['id', 'brand_id'])->with(['categories:categories.id', 'tags:tags.id'])])
            ->whereKey(array_map(fn (CouponItem $item): int => $item->variantId, $items))
            ->get()
            ->keyBy('id');

        $lines = [];

        foreach ($items as $item) {
            $variant = $variants->get($item->variantId);

            if ($variant === null || $variant->product === null) {
                continue;
            }

            $unitPrice = $variant->currentPrice();
            $lines[] = new CouponQuoteLine($variant->id, $item->quantity, $unitPrice, $unitPrice * $item->quantity, $this->reason($terms, $variant));
        }

        $subtotal = array_sum(array_map(fn (CouponQuoteLine $line): int => $line->total, $lines));
        $eligibleSubtotal = array_sum(array_map(fn (CouponQuoteLine $line): int => $line->isEligible() ? $line->total : 0, $lines));

        $problem = match (true) {
            $eligibleSubtotal === 0 => CouponProblem::NoEligibleItems,
            $terms->minOrderAmount !== null && $eligibleSubtotal < $terms->minOrderAmount => CouponProblem::MinOrder,
            default => null,
        };

        if ($problem !== null) {
            return new CouponQuote($lines, $subtotal, $eligibleSubtotal, 0, 0, false, $problem);
        }

        $uncapped = match ($terms->type) {
            CouponType::Percent => $this->roundDown(intdiv($eligibleSubtotal * (int) $terms->value, 100), $eligibleSubtotal),
            CouponType::Fixed => min((int) $terms->value, $eligibleSubtotal),
            CouponType::FreeShipping => 0,
        };
        $discount = $terms->maxDiscount !== null ? min($uncapped, $terms->maxDiscount) : $uncapped;

        return new CouponQuote($lines, $subtotal, $eligibleSubtotal, $uncapped, $discount, $terms->type === CouponType::FreeShipping, null);
    }

    private function reason(CouponTerms $terms, ProductVariant $variant): ?CouponLineReason
    {
        $product = $variant->product;

        if (in_array($product->id, $terms->excludedProductIds, true)) {
            return CouponLineReason::Excluded;
        }

        if ($terms->appliesTo === CouponAppliesTo::Selected && ! $this->isTargeted($terms, $variant)) {
            return CouponLineReason::NotTargeted;
        }

        if ($terms->excludeDiscounted && $variant->isDiscounted()) {
            return CouponLineReason::Discounted;
        }

        return null;
    }

    private function isTargeted(CouponTerms $terms, ProductVariant $variant): bool
    {
        $product = $variant->product;

        if (in_array($product->id, $terms->productIds, true)
            || ($product->brand_id !== null && in_array($product->brand_id, $terms->brandIds, true))
            || array_intersect($product->tags->modelKeys(), $terms->tagIds) !== []) {
            return true;
        }

        if ($terms->categoryIds === []) {
            return false;
        }

        $tree = CategoryTree::current();

        foreach ($product->categories->modelKeys() as $categoryId) {
            $branch = [$categoryId, ...array_map(fn ($node): int => $node->id, $tree->ancestors($categoryId))];

            if (array_intersect($branch, $terms->categoryIds) !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whole Rials, or whole Toman when the store shows Toman; never more than $limit.
     */
    private function roundDown(int $discount, int $limit): int
    {
        $unit = $this->settings->priceDisplayCurrency()->rialsPerUnit();

        return max(0, min($limit, intdiv($discount, $unit) * $unit));
    }
}

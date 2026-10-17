<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Data\Coupons\CouponItem;
use App\Models\ProductVariant;
use App\Support\Catalog\StorefrontProductQuery;
use Illuminate\Validation\Validator;

/**
 * A cart (for coupons and shipping): `items` `[{ variant_id, quantity }]`, each variant once.
 */
trait HasCouponItemFields
{
    /** The most lines a cart sends. */
    private const int MAX_ITEMS = 100;

    private const int MAX_QUANTITY = 10_000;

    /**
     * @param  list<mixed>  $variantRules  more rules for each `variant_id`
     * @return array<string, list<mixed>>
     */
    protected function couponItemRules(array $variantRules = []): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:'.self::MAX_ITEMS],
            'items.*' => ['array:variant_id,quantity'],
            'items.*.variant_id' => ['required', 'integer', 'distinct', ...$variantRules],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:'.self::MAX_QUANTITY],
        ];
    }

    /**
     * @return list<CouponItem>
     */
    protected function couponItems(): array
    {
        return array_values(array_map(
            fn (array $item): CouponItem => new CouponItem((int) $item['variant_id'], (int) $item['quantity']),
            (array) $this->input('items', []),
        ));
    }

    /**
     * Storefront carts: every item must be an enabled variant of a product the storefront shows.
     */
    protected function checkItemsOnSale(Validator $validator): void
    {
        $ids = array_map(fn (CouponItem $item): int => $item->variantId, $this->couponItems());
        $available = ProductVariant::query()
            ->whereKey($ids)
            ->where('is_enabled', true)
            ->whereIn('product_id', StorefrontProductQuery::visible()->select('products.id'))
            ->pluck('id')
            ->all();

        foreach ($ids as $i => $id) {
            if (! in_array($id, $available, true)) {
                $validator->errors()->add("items.{$i}.variant_id", __('coupons.errors.item_unavailable'));
            }
        }
    }
}

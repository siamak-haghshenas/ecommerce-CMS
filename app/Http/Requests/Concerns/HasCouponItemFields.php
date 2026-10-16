<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Data\Coupons\CouponItem;

/**
 * The cart a coupon is worked out for: `items` `[{ variant_id, quantity }]`, each variant once.
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
}

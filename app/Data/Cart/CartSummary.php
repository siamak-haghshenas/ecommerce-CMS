<?php

declare(strict_types=1);

namespace App\Data\Cart;

use App\Data\Coupons\CouponItem;
use App\Models\Cart;

/**
 * A cart worked out now: its lines with their prices and problems, the coupon and the totals.
 * Amounts are Rials; lines that can't be bought count in no total.
 */
final readonly class CartSummary
{
    /**
     * @param  list<CartLine>  $lines  in the order they were added
     */
    public function __construct(
        public Cart $cart,
        public array $lines,
        public ?CartCoupon $coupon,
    ) {}

    /** Units in the cart, every line included (the header badge). */
    public function itemsCount(): int
    {
        return array_sum(array_map(fn (CartLine $line): int => $line->quantity(), $this->lines));
    }

    /** The items at their regular prices. */
    public function regularSubtotal(): int
    {
        return array_sum(array_map(fn (CartLine $line): int => $line->regularTotal(), $this->lines));
    }

    /** The items at the prices shoppers pay now (sales, sale events and promotions applied). */
    public function itemsSubtotal(): int
    {
        return array_sum(array_map(fn (CartLine $line): int => $line->total(), $this->lines));
    }

    /** What sales, sale events and promotions take off the regular prices. */
    public function savings(): int
    {
        return $this->regularSubtotal() - $this->itemsSubtotal();
    }

    public function couponDiscount(): int
    {
        return $this->coupon?->discount() ?? 0;
    }

    /** The items after the coupon, before shipping. */
    public function total(): int
    {
        return $this->itemsSubtotal() - $this->couponDiscount();
    }

    /**
     * At least one line, and none the customer must change first. A coupon that doesn't apply
     * takes nothing off but doesn't block.
     */
    public function canCheckout(): bool
    {
        return $this->lines !== [] && ! in_array(true, array_map(fn (CartLine $line): bool => $line->blocksCheckout(), $this->lines), true);
    }

    /**
     * The lines that can be bought, as coupons and shipping see a cart.
     *
     * @return list<CouponItem>
     */
    public function purchasableItems(): array
    {
        return array_values(array_map(
            fn (CartLine $line): CouponItem => new CouponItem($line->item->product_variant_id, $line->quantity()),
            array_filter($this->lines, fn (CartLine $line): bool => $line->isPurchasable()),
        ));
    }
}

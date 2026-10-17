<?php

declare(strict_types=1);

namespace App\Data\Shipping;

/**
 * A cart measured for shipping. Amounts are Rials. `total` is what the customer pays for the
 * items (current prices minus the coupon discount); the minimum order and "free over" amounts
 * are compared with it.
 */
final readonly class ShippingBasket
{
    /**
     * @param  list<ShippingBasketLine>  $lines
     * @param  int|null  $weightGrams  null: a product has no weight and the store has no default
     * @param  int|null  $volumeCm3  null: a product is missing a dimension
     */
    public function __construct(
        public array $lines,
        public int $subtotal,
        public int $couponDiscount,
        public bool $freeShippingCoupon,
        public ?int $weightGrams,
        public ?int $volumeCm3,
    ) {}

    public function total(): int
    {
        return $this->subtotal - $this->couponDiscount;
    }
}

<?php

declare(strict_types=1);

namespace App\Data\Shipping;

/**
 * A cart line as shipping sees it: its price and the size of one unit.
 */
final readonly class ShippingBasketLine
{
    /**
     * @param  int|null  $unitWeightGrams  null: no weight and no store default
     * @param  bool  $usesDefaultWeight  the product has no weight; the store default was used
     * @param  int|null  $unitVolumeCm3  null: a dimension is missing
     */
    public function __construct(
        public int $variantId,
        public int $quantity,
        public int $unitPrice,
        public ?int $unitWeightGrams,
        public bool $usesDefaultWeight,
        public ?int $unitVolumeCm3,
    ) {}
}

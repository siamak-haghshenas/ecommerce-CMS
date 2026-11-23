<?php

declare(strict_types=1);

namespace App\Data\Cart;

use App\Enums\CartLineProblem;
use App\Models\CartItem;
use App\Models\ProductVariant;

/**
 * A cart line as the storefront sells it now. Amounts are Rials; a line that can't be bought
 * (unavailable or out of stock) counts in no total.
 */
final readonly class CartLine
{
    /**
     * @param  int|null  $priceWas  the price the customer saw before, when it changed
     * @param  list<CartLineProblem>  $problems
     */
    public function __construct(
        public CartItem $item,
        public ?ProductVariant $variant,
        public int $maxQuantity,
        public int $unitPrice,
        public int $regularPrice,
        public ?int $priceWas,
        public array $problems,
    ) {}

    public function quantity(): int
    {
        return $this->item->quantity;
    }

    public function isPurchasable(): bool
    {
        return ! in_array(CartLineProblem::Unavailable, $this->problems, true)
            && ! in_array(CartLineProblem::OutOfStock, $this->problems, true);
    }

    /**
     * Unavailable, out of stock or above its limit: the customer must change it before checkout.
     * (A line a merge cut to its limit, and a changed price, are only notices.)
     */
    public function blocksCheckout(): bool
    {
        return ! $this->isPurchasable() || $this->quantity() > $this->maxQuantity;
    }

    public function total(): int
    {
        return $this->isPurchasable() ? $this->unitPrice * $this->quantity() : 0;
    }

    public function regularTotal(): int
    {
        return $this->isPurchasable() ? $this->regularPrice * $this->quantity() : 0;
    }
}

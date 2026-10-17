<?php

declare(strict_types=1);

namespace App\Data\Shipping;

/**
 * The shipping methods a customer can choose for their cart and city.
 */
final readonly class ShippingQuote
{
    /**
     * @param  list<QuotedShipping>  $options  available methods only, in the admin's order
     */
    public function __construct(
        public ShippingBasket $basket,
        public array $options,
    ) {}
}

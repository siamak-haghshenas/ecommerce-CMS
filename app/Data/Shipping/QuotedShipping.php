<?php

declare(strict_types=1);

namespace App\Data\Shipping;

use App\Models\ShippingMethod;

/**
 * A method a customer can choose for their cart, with its fee.
 */
final readonly class QuotedShipping
{
    public function __construct(
        public ShippingMethod $method,
        public ShippingOption $option,
    ) {}
}

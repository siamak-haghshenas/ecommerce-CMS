<?php

declare(strict_types=1);

namespace App\Data\Shipping;

/**
 * A shipping method as the admin saves it.
 */
final readonly class ShippingMethodData
{
    public function __construct(
        public string $name,
        public ?string $description,
        public ?int $deliveryDaysMin,
        public ?int $deliveryDaysMax,
        public ShippingTerms $terms,
    ) {}
}

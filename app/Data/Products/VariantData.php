<?php

declare(strict_types=1);

namespace App\Data\Products;

use Carbon\CarbonImmutable;

/**
 * One variant (SKU) as sent by the product form. Amounts are whole Rials.
 */
final readonly class VariantData
{
    /**
     * @param  list<int>  $optionValueIds  one value per product option (none for a simple product)
     * @param  int|null  $stockQuantity  the new quantity, or null to leave stock unchanged
     * @param  int|null  $stockQuantityWas  for an existing variant: the quantity the admin saw, to detect concurrent changes
     */
    public function __construct(
        public ?int $id,
        public string $sku,
        public int $price,
        public ?int $salePrice,
        public ?CarbonImmutable $saleStartsAt,
        public ?CarbonImmutable $saleEndsAt,
        public bool $isEnabled,
        public ?int $imageMediaId,
        public array $optionValueIds,
        public ?int $stockQuantity,
        public ?int $stockQuantityWas,
    ) {}
}

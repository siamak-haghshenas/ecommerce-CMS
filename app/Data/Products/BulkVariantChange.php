<?php

declare(strict_types=1);

namespace App\Data\Products;

/**
 * One variant changed in the bulk editor. Amounts are whole Rials.
 */
final readonly class BulkVariantChange
{
    /**
     * @param  array{price?: int, sale_price?: int|null, is_enabled?: bool}  $attributes  only the changed columns
     * @param  int|null  $stockQuantity  the new quantity, or null to leave stock unchanged
     * @param  int|null  $stockQuantityWas  the quantity the admin saw, to detect concurrent changes
     */
    public function __construct(
        public int $variantId,
        public array $attributes = [],
        public ?int $stockQuantity = null,
        public ?int $stockQuantityWas = null,
    ) {}
}

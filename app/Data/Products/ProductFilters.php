<?php

declare(strict_types=1);

namespace App\Data\Products;

use App\Enums\ProductStatus;

/**
 * Filters of the admin product list.
 */
final readonly class ProductFilters
{
    public function __construct(
        public ?string $search = null,
        public ?ProductStatus $status = null,
        public ?int $categoryId = null,
        public ?int $brandId = null,
        public ?int $tagId = null,
        /** "low" (at or below the threshold) or "out" (nothing left to sell). */
        public ?string $stock = null,
        public bool $needsSeo = false,
        /** Show deleted products instead. */
        public bool $trashed = false,
        public string $sort = 'newest',
        public int $perPage = 20,
    ) {}
}

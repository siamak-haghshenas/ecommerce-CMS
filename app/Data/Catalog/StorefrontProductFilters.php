<?php

declare(strict_types=1);

namespace App\Data\Catalog;

/**
 * Filters of the public product list, with the storefront's slugs already resolved to ids.
 */
final readonly class StorefrontProductFilters
{
    /**
     * @param  array<int, list<int>|array{min: float|null, max: float|null}|bool>  $attributes  by attribute id: value ids ("select", any of them), a range ("number") or true/false ("boolean")
     * @param  array<int, list<int>>  $options  by option id: value ids (any of them); one enabled variant must match every option
     */
    public function __construct(
        public ?string $search = null,
        public ?int $categoryId = null,
        public ?int $brandId = null,
        public ?int $tagId = null,
        public array $attributes = [],
        public array $options = [],
        /** Rials, on the current (sale-aware) price of enabled variants. */
        public ?int $minPrice = null,
        public ?int $maxPrice = null,
        public bool $inStock = false,
        public bool $onSale = false,
        public string $sort = 'newest',
        public int $perPage = 24,
    ) {}
}

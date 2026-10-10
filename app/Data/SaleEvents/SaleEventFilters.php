<?php

declare(strict_types=1);

namespace App\Data\SaleEvents;

/**
 * Filters of the admin sale event list (not paginated: the order is managed by hand).
 */
final readonly class SaleEventFilters
{
    public function __construct(
        public ?string $search = null,
        /** "active", "scheduled", "expired" or "disabled". */
        public ?string $state = null,
    ) {}
}

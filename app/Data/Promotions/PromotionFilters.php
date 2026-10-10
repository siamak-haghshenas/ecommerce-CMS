<?php

declare(strict_types=1);

namespace App\Data\Promotions;

/**
 * Filters of the admin promotion list.
 */
final readonly class PromotionFilters
{
    public function __construct(
        public ?string $search = null,
        /** "active", "scheduled", "expired" or "disabled". */
        public ?string $state = null,
        public int $perPage = 20,
    ) {}
}

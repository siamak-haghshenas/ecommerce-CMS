<?php

declare(strict_types=1);

namespace App\Data\Coupons;

final readonly class CouponFilters
{
    /**
     * @param  'active'|'scheduled'|'used_up'|'expired'|'disabled'|null  $state
     */
    public function __construct(
        public ?string $search = null,
        public ?string $state = null,
        public int $perPage = 20,
    ) {}
}

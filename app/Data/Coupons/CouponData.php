<?php

declare(strict_types=1);

namespace App\Data\Coupons;

use App\Enums\CouponCustomerScope;
use Carbon\CarbonImmutable;

/**
 * A coupon as the admin saves it: its code, its terms (what it takes off and from which items),
 * who may use it, its limits and dates.
 */
final readonly class CouponData
{
    /**
     * @param  list<int>  $customerIds  for `selected` only (empty otherwise)
     */
    public function __construct(
        public string $code,
        public string $name,
        public CouponTerms $terms,
        public CouponCustomerScope $customerScope,
        public array $customerIds,
        public ?int $usageLimit,
        public ?int $perCustomerLimit,
        public bool $firstOrderOnly,
        public ?CarbonImmutable $startsAt,
        public ?CarbonImmutable $endsAt,
        public bool $isActive,
    ) {}
}

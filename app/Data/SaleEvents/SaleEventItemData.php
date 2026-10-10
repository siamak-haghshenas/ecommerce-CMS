<?php

declare(strict_types=1);

namespace App\Data\SaleEvents;

use App\Enums\PromotionType;
use Carbon\CarbonImmutable;

/**
 * One variant's discount in a sale event.
 */
final readonly class SaleEventItemData
{
    public function __construct(
        public int $variantId,
        public PromotionType $discountType,
        /** A percentage (1–90) or Rials off each unit. */
        public int $discountValue,
        /** Null: the event's start. */
        public ?CarbonImmutable $startsAt,
        /** Null: the event's end. */
        public ?CarbonImmutable $endsAt,
    ) {}
}

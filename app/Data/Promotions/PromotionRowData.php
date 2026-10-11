<?php

declare(strict_types=1);

namespace App\Data\Promotions;

use App\Enums\PromotionType;
use Carbon\CarbonImmutable;

/**
 * One promotion row as saved: a new one (id null) or an existing one.
 */
final readonly class PromotionRowData
{
    public function __construct(
        public ?int $id,
        public int $variantId,
        public PromotionType $discountType,
        /** A percentage (1–90) or Rials off each unit. */
        public int $discountValue,
        /** Null: no limit. */
        public ?int $maxPerOrder,
        /** Null: no limit. */
        public ?int $quantityLimit,
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
    ) {}
}

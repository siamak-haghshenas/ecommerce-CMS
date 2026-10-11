<?php

declare(strict_types=1);

namespace App\Data\Pricing;

use App\Enums\DiscountSource;
use Carbon\CarbonInterface;

/**
 * A variant's price at one moment, and the next moment it may change.
 */
final readonly class VariantPrice
{
    public function __construct(
        /** Rials. */
        public int $current,
        /** Null: the regular price. */
        public ?DiscountSource $source,
        /** The next sale, sale event or promotion start or end that may change the price; null: none. */
        public ?CarbonInterface $recalcAt,
        public ?int $saleEventId = null,
        /** The most units of the variant one order may hold at this price (the event's or promotion's limit); null: no limit. */
        public ?int $maxPerOrder = null,
        public ?int $promotionId = null,
    ) {}
}

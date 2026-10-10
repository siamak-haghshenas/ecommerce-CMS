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
        public ?int $promotionId,
        /** The next sale, promotion or sale event start or end that may change the price; null: none. */
        public ?CarbonInterface $recalcAt,
        public ?int $saleEventId = null,
    ) {}
}

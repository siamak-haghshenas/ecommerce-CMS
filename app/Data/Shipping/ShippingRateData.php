<?php

declare(strict_types=1);

namespace App\Data\Shipping;

use App\Models\ShippingRate;

/**
 * A weight (grams) or volume (cubic centimetres) range and its fee in Rials. `start` is
 * included, `end` excluded; a null end has no upper limit.
 */
final readonly class ShippingRateData
{
    public function __construct(
        public int $start,
        public ?int $end,
        public int $fee,
    ) {}

    public static function of(ShippingRate $rate): self
    {
        return new self($rate->range_start, $rate->range_end, $rate->fee);
    }

    public function covers(int $measure): bool
    {
        return $measure >= $this->start && ($this->end === null || $measure < $this->end);
    }
}

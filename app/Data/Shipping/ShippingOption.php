<?php

declare(strict_types=1);

namespace App\Data\Shipping;

use App\Enums\ShippingFreeReason;
use App\Enums\ShippingUnavailableReason;

/**
 * A shipping method worked out for a basket. Amounts are Rials. Unavailable options have a
 * reason and no fee.
 */
final readonly class ShippingOption
{
    /**
     * @param  int|null  $measure  the order's weight (grams, packaging included) or volume (cm³) the range was chosen by; null for couriers
     * @param  int|null  $originalFee  the fee before free shipping
     * @param  int|null  $fee  what shipping costs: 0 when free
     * @param  int|null  $remainingForFree  how much more the order needs for "free over" (null: not applicable)
     */
    public function __construct(
        public ?ShippingUnavailableReason $reason,
        public ?int $measure = null,
        public ?ShippingRateData $rate = null,
        public ?int $originalFee = null,
        public ?int $fee = null,
        public ?ShippingFreeReason $freeReason = null,
        public bool $feeOnDelivery = false,
        public ?int $remainingForFree = null,
    ) {}

    public function isAvailable(): bool
    {
        return $this->reason === null;
    }

    /**
     * Paid online with the order.
     */
    public function payableNow(): int
    {
        return $this->feeOnDelivery ? 0 : (int) $this->fee;
    }

    /**
     * Collected by the carrier on delivery.
     */
    public function collectOnDelivery(): int
    {
        return $this->feeOnDelivery ? (int) $this->fee : 0;
    }
}

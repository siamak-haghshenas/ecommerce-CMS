<?php

declare(strict_types=1);

namespace App\Data\Shipping;

use App\Enums\ShippingMethodType;
use App\Models\ShippingMethod;
use App\Models\ShippingRate;

/**
 * What a shipping method's fee depends on, saved or not (the editor's test box sends unsaved
 * ones). Amounts are Rials.
 */
final readonly class ShippingTerms
{
    /**
     * @param  list<ShippingRateData>  $rates  weight or volume methods, by start
     * @param  array<int, int|null>  $cityFees  courier methods: city id => its own fee (null: the default fee)
     */
    public function __construct(
        public ShippingMethodType $type,
        public bool $isActive,
        public bool $feeOnDelivery,
        public ?int $defaultFee,
        public ?int $packagingWeightGrams,
        public ?int $minOrderAmount,
        public ?int $freeOverAmount,
        public array $rates,
        public array $cityFees,
    ) {}

    /**
     * A saved method, with `rates` and `cities` loaded (the cities may be limited to the ones asked about).
     */
    public static function of(ShippingMethod $method): self
    {
        $cityFees = [];

        foreach ($method->cities as $city) {
            $cityFees[$city->id] = $city->shippingFee();
        }

        return new self(
            type: $method->type,
            isActive: $method->is_active,
            feeOnDelivery: $method->fee_on_delivery,
            defaultFee: $method->default_fee,
            packagingWeightGrams: $method->packaging_weight_grams,
            minOrderAmount: $method->min_order_amount,
            freeOverAmount: $method->free_over_amount,
            rates: $method->rates->map(fn (ShippingRate $rate): ShippingRateData => ShippingRateData::of($rate))->values()->all(),
            cityFees: $cityFees,
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Data\Shipping\ShippingMethodData;
use App\Data\Shipping\ShippingRateData;
use App\Data\Shipping\ShippingTerms;
use App\Enums\ShippingMethodType;
use App\Models\ShippingMethod;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A shipping method as the editor sends it (the save request, and the test box under `method.`).
 * Amounts are Rials; ranges are grams (weight) or cubic centimetres (volume). Fields of another
 * type must be empty (null) or left out: `rates` for couriers, `cities` and `default_fee` for weight and volume
 * methods, `packaging_weight_grams` for anything but weight.
 */
trait HasShippingMethodFields
{
    private const int MAX_AMOUNT = 1_000_000_000_000;

    /** 1,000 tonnes or 1,000 m³: far beyond any parcel. */
    private const int MAX_MEASURE = 1_000_000_000;

    /**
     * @return array<string, list<mixed>>
     */
    protected function shippingMethodRules(string $prefix = ''): array
    {
        $type = $prefix.'type';
        $courier = ShippingMethodType::Courier->value;
        $fee = ['integer', 'min:0', 'max:'.self::MAX_AMOUNT];

        return [
            $prefix.'name' => ['required', 'string', 'max:100'],
            $prefix.'description' => ['nullable', 'string', 'max:500'],
            $type => ['required', Rule::enum(ShippingMethodType::class)],
            $prefix.'fee_on_delivery' => ['boolean'],
            $prefix.'default_fee' => ["required_if:{$type},{$courier}", "prohibited_unless:{$type},{$courier}", 'nullable', ...$fee],
            $prefix.'packaging_weight_grams' => ["prohibited_unless:{$type},".ShippingMethodType::Weight->value, 'nullable', 'integer', 'min:0', 'max:100000'],
            $prefix.'min_order_amount' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_AMOUNT],
            $prefix.'free_over_amount' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_AMOUNT],
            $prefix.'delivery_days_min' => ['nullable', 'integer', 'min:0', 'max:365'],
            $prefix.'delivery_days_max' => ['nullable', 'integer', 'min:0', 'max:365'],
            $prefix.'is_active' => ['boolean'],
            $prefix.'rates' => ["prohibited_if:{$type},{$courier}", "required_unless:{$type},{$courier}", 'nullable', 'array', 'max:'.ShippingMethod::MAX_RATES],
            $prefix.'rates.*' => ['array:start,end,fee'],
            $prefix.'rates.*.start' => ['required', 'integer', 'min:0', 'max:'.self::MAX_MEASURE],
            $prefix.'rates.*.end' => ['nullable', 'integer', 'min:0', 'max:'.self::MAX_MEASURE],
            $prefix.'rates.*.fee' => ['required', ...$fee],
            $prefix.'cities' => ["prohibited_unless:{$type},{$courier}", "required_if:{$type},{$courier}", 'nullable', 'array', 'max:'.ShippingMethod::MAX_CITIES],
            $prefix.'cities.*' => ['array:id,fee'],
            $prefix.'cities.*.id' => ['required', 'integer', 'distinct', Rule::exists('cities', 'id')],
            $prefix.'cities.*.fee' => ['nullable', ...$fee],
        ];
    }

    /**
     * Ranges start at 0 and follow each other without gaps or overlaps (each starts where the
     * previous one ended); only the last may have no end. Delivery days run from min to max.
     */
    protected function checkShippingMethod(Validator $validator, string $prefix = ''): void
    {
        $min = $this->input($prefix.'delivery_days_min');
        $max = $this->input($prefix.'delivery_days_max');

        if ($min !== null && $max !== null && (int) $max < (int) $min) {
            $validator->errors()->add($prefix.'delivery_days_max', __('shipping.errors.days_order'));
        }

        $rates = array_values((array) $this->input($prefix.'rates', []));
        $last = count($rates) - 1;

        foreach ($rates as $i => $rate) {
            $start = (int) $rate['start'];
            $end = $rate['end'] ?? null;
            $expectedStart = $i === 0 ? 0 : $rates[$i - 1]['end'] ?? null;

            if ($expectedStart !== null && $start !== (int) $expectedStart) {
                $validator->errors()->add("{$prefix}rates.{$i}.start", $i === 0
                    ? __('shipping.errors.first_range_start')
                    : __('shipping.errors.range_gap'));
            }

            if ($end === null && $i !== $last) {
                $validator->errors()->add("{$prefix}rates.{$i}.end", __('shipping.errors.range_end_required'));
            } elseif ($end !== null && (int) $end <= $start) {
                $validator->errors()->add("{$prefix}rates.{$i}.end", __('shipping.errors.range_end_after_start'));
            }
        }
    }

    /**
     * @param  bool  $forceActive  the test box prices a method that isn't switched on yet
     */
    protected function shippingTerms(string $prefix = '', bool $forceActive = false): ShippingTerms
    {
        $type = ShippingMethodType::from($this->string($prefix.'type')->toString());
        $integer = fn (string $key): ?int => $this->filled($prefix.$key) ? $this->integer($prefix.$key) : null;

        $cityFees = [];

        foreach ((array) $this->input($prefix.'cities', []) as $city) {
            $cityFees[(int) $city['id']] = isset($city['fee']) ? (int) $city['fee'] : null;
        }

        return new ShippingTerms(
            type: $type,
            isActive: $forceActive || $this->boolean($prefix.'is_active'),
            feeOnDelivery: $this->boolean($prefix.'fee_on_delivery'),
            defaultFee: $type === ShippingMethodType::Courier ? $integer('default_fee') : null,
            packagingWeightGrams: $type === ShippingMethodType::Weight ? $integer('packaging_weight_grams') : null,
            minOrderAmount: $integer('min_order_amount'),
            freeOverAmount: $integer('free_over_amount'),
            rates: $type->hasRanges() ? array_values(array_map(
                fn (array $rate): ShippingRateData => new ShippingRateData((int) $rate['start'], isset($rate['end']) ? (int) $rate['end'] : null, (int) $rate['fee']),
                (array) $this->input($prefix.'rates', []),
            )) : [],
            cityFees: $type === ShippingMethodType::Courier ? $cityFees : [],
        );
    }

    protected function shippingMethodData(string $prefix = ''): ShippingMethodData
    {
        $integer = fn (string $key): ?int => $this->filled($prefix.$key) ? $this->integer($prefix.$key) : null;

        return new ShippingMethodData(
            name: trim($this->string($prefix.'name')->toString()),
            description: $this->filled($prefix.'description') ? trim($this->string($prefix.'description')->toString()) : null,
            deliveryDaysMin: $integer('delivery_days_min'),
            deliveryDaysMax: $integer('delivery_days_max'),
            terms: $this->shippingTerms($prefix),
        );
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ShippingMethodType;
use App\Models\City;
use App\Models\ShippingMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShippingMethod>
 */
class ShippingMethodFactory extends Factory
{
    /**
     * An active weight method without ranges (add them with withRates()).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'type' => ShippingMethodType::Weight,
            'fee_on_delivery' => false,
            'is_active' => true,
            'position' => 0,
        ];
    }

    /**
     * @param  list<array{0: int, 1: int|null, 2: int}>  $rates  [start, end, fee]
     */
    public function withRates(array $rates): static
    {
        return $this->afterCreating(function (ShippingMethod $method) use ($rates): void {
            foreach ($rates as [$start, $end, $fee]) {
                $method->rates()->create(['range_start' => $start, 'range_end' => $end, 'fee' => $fee]);
            }
        });
    }

    public function volume(): static
    {
        return $this->state(fn (array $attributes) => ['type' => ShippingMethodType::Volume]);
    }

    /**
     * A courier for the given cities: [city, its own fee or null for the default].
     *
     * @param  list<array{0: City, 1: int|null}>  $cities
     */
    public function courier(int $defaultFee, array $cities = []): static
    {
        return $this->state(fn (array $attributes) => ['type' => ShippingMethodType::Courier, 'default_fee' => $defaultFee])
            ->afterCreating(function (ShippingMethod $method) use ($cities): void {
                foreach ($cities as [$city, $fee]) {
                    $method->cities()->attach($city->id, ['fee' => $fee]);
                }
            });
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CouponAppliesTo;
use App\Enums\CouponCustomerScope;
use App\Enums\CouponType;
use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    /**
     * A running 10% coupon for every customer.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('????##')),
            'name' => fake()->words(2, true),
            'type' => CouponType::Percent,
            'value' => 10,
            'applies_to' => CouponAppliesTo::All,
            'customer_scope' => CouponCustomerScope::All,
            'starts_at' => now()->subDay(),
            'ends_at' => null,
            'is_active' => true,
        ];
    }

    /**
     * Only for the customers linked to it.
     */
    public function forSelectedCustomers(): static
    {
        return $this->state(fn (array $attributes) => ['customer_scope' => CouponCustomerScope::Selected]);
    }
}

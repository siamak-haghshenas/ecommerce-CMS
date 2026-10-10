<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PromotionScope;
use App\Enums\PromotionType;
use App\Models\Promotion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Promotion>
 */
class PromotionFactory extends Factory
{
    /**
     * Define the model's default state: 10% off everything, running now.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'type' => PromotionType::Percent,
            'value' => 10,
            'max_discount' => null,
            'scope' => PromotionScope::All,
            'starts_at' => now()->subDay(),
            'ends_at' => null,
            'is_active' => true,
        ];
    }
}

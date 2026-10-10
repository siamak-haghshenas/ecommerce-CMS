<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SaleEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleEvent>
 */
class SaleEventFactory extends Factory
{
    /**
     * Define the model's default state: running from yesterday for a week, no landing page.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addWeek(),
            'is_active' => true,
            'position' => 0,
            'has_landing_page' => false,
        ];
    }

    /**
     * With a landing page at this slug.
     */
    public function withLanding(?string $slug = null): static
    {
        return $this->state(fn (): array => [
            'has_landing_page' => true,
            'slug' => $slug ?? fake()->unique()->slug(2),
            'landing_title' => fake()->sentence(3),
        ]);
    }
}

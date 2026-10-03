<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Brand;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Brand>
 */
class BrandFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'persian_name' => null,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            'description' => null,
            'website' => null,
            'position' => 0,
            'is_active' => true,
            'meta_title' => null,
            'meta_description' => null,
            'focus_keyphrase' => null,
        ];
    }

    public function hidden(): static
    {
        return $this->state(['is_active' => false]);
    }
}

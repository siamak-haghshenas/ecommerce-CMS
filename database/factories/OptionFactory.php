<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OptionDisplayType;
use App\Models\Option;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Option>
 */
class OptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            'display_type' => OptionDisplayType::Text,
            'position' => 0,
        ];
    }

    /**
     * @param  array<string, string>  $values  value => slug
     */
    public function withValues(array $values = ['S' => 's', 'M' => 'm', 'L' => 'l']): static
    {
        return $this->afterCreating(function (Option $option) use ($values): void {
            $position = 0;

            foreach ($values as $value => $slug) {
                $option->values()->create(['value' => $value, 'slug' => $slug, 'position' => $position++]);
            }
        });
    }
}

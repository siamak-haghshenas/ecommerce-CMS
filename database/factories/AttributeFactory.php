<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AttributeType;
use App\Models\Attribute;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Attribute>
 */
class AttributeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            'type' => AttributeType::Text,
            'unit' => null,
            'is_filterable' => false,
            'position' => 0,
        ];
    }

    /**
     * A "select" attribute with the given values (value => slug).
     *
     * @param  array<string, string>  $values
     */
    public function select(array $values = ['Cotton' => 'cotton', 'Linen' => 'linen']): static
    {
        return $this->state(['type' => AttributeType::Select])
            ->afterCreating(function (Attribute $attribute) use ($values): void {
                $position = 0;

                foreach ($values as $value => $slug) {
                    $attribute->values()->create(['value' => $value, 'slug' => $slug, 'position' => $position++]);
                }
            });
    }
}

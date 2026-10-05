<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CategoryType;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
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
            'type' => CategoryType::Product,
            'parent_id' => null,
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            'description' => null,
            'meta_title' => null,
            'meta_description' => null,
            'position' => 0,
            'is_active' => true,
        ];
    }

    public function childOf(Category $parent, int $position = 0): static
    {
        // A tree never mixes types.
        return $this->state(['type' => $parent->type, 'parent_id' => $parent->id, 'position' => $position]);
    }

    public function blog(): static
    {
        return $this->state(['type' => CategoryType::Blog]);
    }

    public function hidden(): static
    {
        return $this->state(['is_active' => false]);
    }
}

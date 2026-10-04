<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PageStatus;
use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(3);

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1, 99999),
            'excerpt' => null,
            'content' => '<p>'.fake()->paragraph().'</p>',
            'status' => PageStatus::Published,
            'published_at' => now()->subDay(),
            'meta_title' => null,
            'meta_description' => null,
            'focus_keyphrase' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => PageStatus::Draft, 'published_at' => null]);
    }

    public function scheduled(): static
    {
        return $this->state(['status' => PageStatus::Published, 'published_at' => now()->addWeek()]);
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MediaType;
use App\Models\MediaAsset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A library file's record (without a stored file; tests that need one upload it).
 *
 * @extends Factory<MediaAsset>
 */
class MediaAssetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'folder_id' => null,
            'type' => MediaType::Image,
            'name' => fake()->word().'.jpg',
            'mime_type' => 'image/jpeg',
            'size' => fake()->numberBetween(10_000, 900_000),
            'width' => 800,
            'height' => 600,
            'title' => null,
            'alt' => null,
            'cover_id' => null,
            'uploaded_by' => null,
        ];
    }

    public function video(): static
    {
        return $this->state(['type' => MediaType::Video, 'name' => fake()->word().'.mp4', 'mime_type' => 'video/mp4', 'width' => null, 'height' => null]);
    }

    public function document(): static
    {
        return $this->state(['type' => MediaType::Document, 'name' => fake()->word().'.pdf', 'mime_type' => 'application/pdf', 'width' => null, 'height' => null]);
    }
}

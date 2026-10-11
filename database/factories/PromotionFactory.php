<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PromotionType;
use App\Models\ProductVariant;
use App\Models\Promotion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Promotion>
 */
class PromotionFactory extends Factory
{
    /**
     * Define the model's default state: 20% off a new variant, running for a week.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_variant_id' => ProductVariant::factory(),
            'product_id' => fn (array $attributes): int => (int) ProductVariant::query()->findOrFail($attributes['product_variant_id'])->product_id,
            'discount_type' => PromotionType::Percent,
            'discount_value' => 20,
            'max_per_order' => null,
            'quantity_limit' => null,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addWeek(),
        ];
    }

    /**
     * On this variant.
     */
    public function forVariant(ProductVariant $variant): static
    {
        return $this->state(['product_variant_id' => $variant->id, 'product_id' => $variant->product_id]);
    }
}

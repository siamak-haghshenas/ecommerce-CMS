<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'type' => ProductType::Simple,
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 999999),
            'short_description' => null,
            'description' => null,
            'status' => ProductStatus::Draft,
            'published_at' => null,
            'brand_id' => null,
            'track_stock' => true,
            'allow_backorders' => false,
            'low_stock_threshold' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(['status' => ProductStatus::Published, 'published_at' => now()->subDay()]);
    }

    /**
     * A simple product with its single variant.
     */
    public function withVariant(int $price = 1_000_000, int $stock = 10, ?string $sku = null): static
    {
        return $this->afterCreating(function (Product $product) use ($price, $stock, $sku): void {
            ProductVariant::factory()->for($product)->create([
                'sku' => $sku ?? 'SKU-'.$product->id,
                'price' => $price,
            ])->forceFill(['stock_quantity' => $stock])->save();
        });
    }

    public function inCategory(Category $category): static
    {
        return $this->afterCreating(fn (Product $product) => $product->categories()->attach($category, ['is_primary' => true]));
    }
}

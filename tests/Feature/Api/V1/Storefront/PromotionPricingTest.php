<?php

declare(strict_types=1);

use App\Actions\Pricing\RefreshPricesAction;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Support\Settings;

use function Pest\Laravel\getJson;

beforeEach(function () {
    app(Settings::class)->set([Settings::PRICE_DISPLAY_CURRENCY => 'IRR']);
});

function promotedProduct(string $slug, int $price): Product
{
    $product = Product::factory()->published()->create(['slug' => $slug]);
    $product->categories()->attach(Category::factory()->create(), ['is_primary' => true]);
    ProductVariant::factory()->for($product)->create(['price' => $price]);

    return $product;
}

it('shows the promotion price, its source and the promotion on the product page', function () {
    promotedProduct('shoe', 200_000);
    Promotion::factory()->create(['name' => 'Yalda', 'value' => 25, 'ends_at' => now()->addWeek()]);
    app(RefreshPricesAction::class)->handle();

    getJson(route('api.v1.products.show', 'shoe'))
        ->assertOk()
        ->assertJsonPath('data.variants.0.price', 150_000)
        ->assertJsonPath('data.variants.0.regular_price', 200_000)
        ->assertJsonPath('data.variants.0.discount_percent', 25)
        ->assertJsonPath('data.variants.0.discount_source', 'promotion')
        ->assertJsonPath('data.variants.0.promotion.name', 'Yalda')
        ->assertJsonPath('data.variants.0.sale_ends_at', null);
});

it('filters, sorts and counts on the promotion price', function () {
    $promoted = promotedProduct('promoted', 300_000);
    $plain = promotedProduct('plain', 200_000);
    Promotion::factory()->create(['scope' => 'selected', 'value' => 50])
        ->targets()->create(['target_type' => 'product', 'target_id' => $promoted->id, 'is_excluded' => false]);
    app(RefreshPricesAction::class)->handle();

    getJson(route('api.v1.products.index', ['max_price' => 160_000]))->assertJsonPath('data.*.id', [$promoted->id]);
    getJson(route('api.v1.products.index', ['on_sale' => 1]))->assertJsonPath('data.*.id', [$promoted->id]);
    getJson(route('api.v1.products.index', ['sort' => 'price_asc']))->assertJsonPath('data.*.id', [$promoted->id, $plain->id]);
    getJson(route('api.v1.products.filters'))->assertJsonPath('data.price', ['min' => 150_000, 'max' => 200_000]);
});

<?php

declare(strict_types=1);

use App\Actions\Pricing\RefreshPricesAction;
use App\Enums\PromotionType;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SaleEvent;
use App\Models\User;
use App\Support\PreviewToken;
use App\Support\Settings;

use function Pest\Laravel\getJson;
use function Pest\Laravel\travel;

beforeEach(function () {
    app(Settings::class)->set([Settings::PRICE_DISPLAY_CURRENCY => 'IRR']);
});

/**
 * A visible product (slug $slug) with one variant at $price, put in $event at $percent off.
 */
function landingProduct(SaleEvent $event, string $slug, int $price = 100_000, int $percent = 20): ProductVariant
{
    $product = Product::factory()->published()->create(['slug' => $slug]);
    $product->categories()->attach(Category::factory()->create(), ['is_primary' => true]);
    $variant = ProductVariant::factory()->for($product)->create(['price' => $price]);
    $event->items()->create([
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'discount_type' => PromotionType::Percent,
        'discount_value' => $percent,
    ]);
    app(RefreshPricesAction::class)->handle([$product->id]);

    return $variant;
}

it('shows the event price and the event on the product page', function () {
    $event = SaleEvent::factory()->withLanding('yalda')->create(['name' => 'Yalda night']);
    landingProduct($event, 'shoe');

    getJson(route('api.v1.products.show', 'shoe'))
        ->assertOk()
        ->assertJsonPath('data.variants.0.price', 80_000)
        ->assertJsonPath('data.variants.0.discount_source', 'sale_event')
        ->assertJsonPath('data.variants.0.sale_event.name', 'Yalda night')
        ->assertJsonPath('data.variants.0.sale_event.slug', 'yalda')
        ->assertJsonPath('data.variants.0.sale_event.ends_at', $event->ends_at->toIso8601String())
        ->assertJsonPath('data.variants.0.promotion', null);
});

it('goes through upcoming, running and ended, with prices only while running', function () {
    $event = SaleEvent::factory()->withLanding('weekend')->create(['starts_at' => now()->addDay(), 'ends_at' => now()->addDays(2)]);
    $variant = landingProduct($event, 'shoe');

    getJson(route('api.v1.sale-events.show', 'weekend'))->assertOk()->assertJsonPath('data.status', 'upcoming');
    expect($variant->fresh()?->current_price)->toBe(100_000);

    travel(25)->hours();
    $this->artisan('prices:refresh --due')->assertSuccessful();
    getJson(route('api.v1.sale-events.show', 'weekend'))->assertJsonPath('data.status', 'running');
    expect($variant->fresh()?->current_price)->toBe(80_000);

    travel(1)->days();
    $this->artisan('prices:refresh --due')->assertSuccessful();
    getJson(route('api.v1.sale-events.show', 'weekend'))->assertOk()->assertJsonPath('data.status', 'ended');
    getJson(route('api.v1.sale-events.index'))->assertJsonCount(0, 'data');
    expect($variant->fresh()?->current_price)->toBe(100_000);
});

it('returns the landing page with its resolved SEO block', function () {
    SaleEvent::factory()->withLanding('yalda')->create([
        'name' => 'Yalda night',
        'landing_title' => 'Yalda night sale',
        'landing_description' => '<p>The longest night of the year.</p>',
        'meta_title' => null,
        'focus_keyphrase' => 'secret',
    ]);

    getJson(route('api.v1.sale-events.show', 'yalda'))
        ->assertOk()
        ->assertJsonPath('data.title', 'Yalda night sale')
        ->assertJsonPath('data.description', '<p>The longest night of the year.</p>')
        ->assertJsonPath('data.seo.title', 'Yalda night sale')
        ->assertJsonPath('data.seo.description', 'The longest night of the year.')
        ->assertJsonMissingPath('data.focus_keyphrase')
        ->assertJsonMissingPath('data.position');
});

it('hides landing pages that are switched off, disabled or unknown', function (array $state) {
    SaleEvent::factory()->withLanding('hidden')->create($state);

    getJson(route('api.v1.sale-events.show', 'hidden'))->assertNotFound();
    getJson(route('api.v1.sale-events.index'))->assertJsonCount(0, 'data');
    getJson(route('api.v1.products.index', ['sale_event' => 'hidden']))->assertJsonCount(0, 'data');
})->with([
    'switched off' => [['has_landing_page' => false]],
    'disabled' => [['is_active' => false]],
]);

it('lists the event’s products with the usual filters', function () {
    $event = SaleEvent::factory()->withLanding('yalda')->create();
    landingProduct($event, 'cheap', 50_000);
    landingProduct($event, 'dear', 300_000);
    $outside = Product::factory()->published()->create();
    $outside->categories()->attach(Category::factory()->create(), ['is_primary' => true]);
    ProductVariant::factory()->for($outside)->create();

    getJson(route('api.v1.products.index', ['sale_event' => 'yalda', 'sort' => 'price_asc']))
        ->assertOk()
        ->assertJsonPath('data.*.slug', ['cheap', 'dear']);
    getJson(route('api.v1.products.index', ['sale_event' => 'yalda', 'max_price' => 100_000]))
        ->assertJsonPath('data.*.slug', ['cheap']);
    getJson(route('api.v1.products.filters', ['sale_event' => 'yalda']))->assertJsonPath('data.total', 2);
});

it('shows a landing page to a preview token whatever its state', function () {
    $event = SaleEvent::factory()->create(['is_active' => false, 'has_landing_page' => false, 'slug' => 'draft']);
    $token = PreviewToken::create(PreviewToken::SALE_EVENT, $event->id, User::factory()->create()->id, now()->toImmutable()->addHour());

    getJson(route('api.v1.sale-events.preview', $token))
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertJsonPath('data.slug', 'draft');

    $pageToken = PreviewToken::create(PreviewToken::PAGE, $event->id, User::factory()->create()->id, now()->toImmutable()->addHour());
    getJson(route('api.v1.sale-events.preview', $pageToken))->assertNotFound();
});

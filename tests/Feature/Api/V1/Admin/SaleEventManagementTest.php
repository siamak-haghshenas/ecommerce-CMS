<?php

declare(strict_types=1);

use App\Enums\DiscountSource;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\MediaUsage;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SaleEvent;
use App\Models\SaleEventItem;
use App\Models\User;
use App\Support\PreviewToken;
use App\Support\Settings;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

beforeEach(function () {
    app(Settings::class)->set([Settings::PRICE_DISPLAY_CURRENCY => 'IRR']);
});

function saleEventManager(UserRole $role = UserRole::Admin): User
{
    return User::factory()->withRole($role)->create();
}

/**
 * A published product with one variant at $price Rials.
 */
function eventVariant(int $price = 100_000, array $attributes = []): ProductVariant
{
    $product = Product::factory()->published()->create();
    $product->categories()->attach(Category::factory()->create(), ['is_primary' => true]);

    return ProductVariant::factory()->for($product)->create(['price' => $price, ...$attributes]);
}

/**
 * @return array<string, mixed>
 */
function saleEventPayload(array $items = [], array $overrides = []): array
{
    return [
        'name' => 'Yalda night',
        'starts_at' => now()->subHour()->toIso8601String(),
        'ends_at' => now()->addDays(3)->toIso8601String(),
        'is_active' => true,
        'items' => $items,
        'has_landing_page' => false,
        'slug' => null,
        ...$overrides,
    ];
}

/**
 * @return array<string, mixed>
 */
function saleEventItemPayload(ProductVariant $variant, string $type = 'percent', int $value = 20, array $overrides = []): array
{
    return ['variant_id' => $variant->id, 'discount_type' => $type, 'discount_value' => $value, 'starts_at' => null, 'ends_at' => null, ...$overrides];
}

describe('access', function () {
    it('returns 401 to guests and 403 to authors', function () {
        getJson(route('api.v1.admin.sale-events.index'))->assertUnauthorized();
        actingAs(saleEventManager(UserRole::Author))->getJson(route('api.v1.admin.sale-events.index'))->assertForbidden();
        actingAs(saleEventManager(UserRole::Author))->postJson(route('api.v1.admin.sale-events.store'), saleEventPayload())->assertForbidden();
    });
});

describe('saving', function () {
    it('creates an event with per-variant discounts, reprices them and logs it', function () {
        $percent = eventVariant(200_000);
        $fixed = eventVariant(80_000);
        $untouched = eventVariant(50_000);

        actingAs(saleEventManager())->postJson(route('api.v1.admin.sale-events.store'), saleEventPayload([
            saleEventItemPayload($percent, 'percent', 25),
            saleEventItemPayload($fixed, 'fixed', 30_000),
        ]))
            ->assertCreated()
            ->assertJsonPath('data.state', 'active')
            ->assertJsonPath('data.items.0.variant.current_price', 150_000)
            ->assertJsonPath('data.items.1.discount_type', 'fixed')
            ->assertJsonPath('data.items.1.variant.current_price', 50_000);

        expect($percent->fresh()?->discount_source)->toBe(DiscountSource::SaleEvent)
            ->and($percent->fresh()?->sale_event_id)->toBe(SaleEvent::query()->sole()->id)
            ->and($untouched->fresh()?->current_price)->toBe(50_000)
            ->and(Activity::query()->where('event', 'sale_event.created')->sole()->properties['name'])->toBe('Yalda night');
    });

    it('rounds event discounts down to whole Toman when the store shows Toman', function () {
        app(Settings::class)->set([Settings::PRICE_DISPLAY_CURRENCY => 'IRT']);
        $variant = eventVariant(123_456);

        actingAs(saleEventManager())->postJson(route('api.v1.admin.sale-events.store'), saleEventPayload([saleEventItemPayload($variant, 'percent', 15)]))
            ->assertCreated();

        // 15% of 123,456 = 18,518.4 → 18,510 Rials.
        expect($variant->fresh()?->current_price)->toBe(123_456 - 18_510);
    });

    it('updates items by variant, removes missing ones and restores their price', function () {
        $kept = eventVariant(100_000);
        $removed = eventVariant(100_000);
        $admin = saleEventManager();
        $id = actingAs($admin)->postJson(route('api.v1.admin.sale-events.store'), saleEventPayload([
            saleEventItemPayload($kept, 'percent', 10),
            saleEventItemPayload($removed, 'percent', 10),
        ]))->json('data.id');
        $keptItemId = SaleEventItem::query()->where('product_variant_id', $kept->id)->value('id');

        actingAs($admin)->putJson(route('api.v1.admin.sale-events.update', $id), saleEventPayload([saleEventItemPayload($kept, 'percent', 30)]))
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $keptItemId);

        expect($kept->fresh()?->current_price)->toBe(70_000)
            ->and($removed->fresh()?->current_price)->toBe(100_000)
            ->and($removed->fresh()?->discount_source)->toBeNull()
            ->and(Activity::query()->where('event', 'sale_event.updated')->sole()->properties['fields'])->toBe(['items']);
    });

    it('saves the landing page with its banner and SEO score', function () {
        $banner = libraryImage();

        actingAs(saleEventManager())->postJson(route('api.v1.admin.sale-events.store'), saleEventPayload(overrides: [
            'has_landing_page' => true,
            'slug' => ' Yalda-Night ',
            'landing_title' => 'Yalda night sale',
            'landing_description' => '<p>The longest night.</p><script>alert(1)</script>',
            'banner_id' => $banner->id,
            'focus_keyphrase' => 'yalda',
        ]))
            ->assertCreated()
            ->assertJsonPath('data.slug', 'yalda-night')
            ->assertJsonPath('data.landing_description', '<p>The longest night.</p>')
            ->assertJsonPath('data.banner.id', $banner->id);

        expect(SaleEvent::query()->sole()->seo_score)->not->toBeNull()
            ->and(MediaUsage::query()->where('media_asset_id', $banner->id)->value('role')?->value)->toBe('banner');
    });

    it('rejects invalid events and items', function (array $item, array $overrides, string $error) {
        $variant = eventVariant(100_000);
        $item = $item === [] ? [] : [saleEventItemPayload($variant, ...$item)];

        actingAs(saleEventManager())->postJson(route('api.v1.admin.sale-events.store'), saleEventPayload($item, $overrides))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$error]);
    })->with([
        'end before start' => [[], ['ends_at' => now()->subDays(2)->toIso8601String()], 'ends_at'],
        'landing page without slug' => [[], ['has_landing_page' => true], 'slug'],
        'slug not Latin' => [[], ['slug' => 'یلدا'], 'slug'],
        'percent over 90' => [['percent', 91], [], 'items.0.discount_value'],
        'amount not below the price' => [['fixed', 100_000], [], 'items.0.discount_value'],
        'item starts before the event' => [['percent', 10, ['starts_at' => now()->subDays(2)->toIso8601String()]], [], 'items.0.starts_at'],
        'item ends after the event' => [['percent', 10, ['ends_at' => now()->addDays(5)->toIso8601String()]], [], 'items.0.ends_at'],
    ]);

    it('refuses the same variant twice and a deleted variant that was not in the event', function () {
        $variant = eventVariant();
        $deleted = eventVariant();
        $deleted->delete();

        actingAs(saleEventManager())->postJson(route('api.v1.admin.sale-events.store'), saleEventPayload([
            saleEventItemPayload($variant),
            saleEventItemPayload($variant),
        ]))->assertJsonValidationErrors(['items.0.variant_id']);

        actingAs(saleEventManager())->postJson(route('api.v1.admin.sale-events.store'), saleEventPayload([saleEventItemPayload($deleted)]))
            ->assertJsonValidationErrors(['items.0.variant_id']);
    });
});

describe('priority', function () {
    it('uses the highest-priority running event, then the lowest price wins against sales and promotions', function () {
        $variant = eventVariant(100_000, ['sale_price' => 85_000]);
        $admin = saleEventManager();
        $small = actingAs($admin)->postJson(route('api.v1.admin.sale-events.store'), saleEventPayload([saleEventItemPayload($variant, 'percent', 10)], ['name' => 'Small']))->json('data.id');
        $big = actingAs($admin)->postJson(route('api.v1.admin.sale-events.store'), saleEventPayload([saleEventItemPayload($variant, 'percent', 40)], ['name' => 'Big']))->json('data.id');

        // "Small" comes first: its 90,000 loses to the sale price (85,000); "Big" is not used.
        expect($variant->fresh()?->current_price)->toBe(85_000)
            ->and($variant->fresh()?->discount_source)->toBe(DiscountSource::Sale);
        actingAs($admin)->getJson(route('api.v1.admin.sale-events.show', $big))
            ->assertJsonPath('data.items.0.overridden_by.0.name', 'Small');

        actingAs($admin)->putJson(route('api.v1.admin.sale-events.order'), ['ids' => [$big, $small]])
            ->assertOk()
            ->assertJsonPath('data.0.id', $big);

        expect($variant->fresh()?->current_price)->toBe(60_000)
            ->and($variant->fresh()?->sale_event_id)->toBe($big)
            ->and(Activity::query()->where('event', 'sale_event.reordered')->exists())->toBeTrue();
    });

    it('falls back to the next event while the higher one is outside the variant’s own dates', function () {
        $variant = eventVariant(100_000);
        $admin = saleEventManager();
        actingAs($admin)->postJson(route('api.v1.admin.sale-events.store'), saleEventPayload([
            saleEventItemPayload($variant, 'percent', 50, ['starts_at' => now()->addDay()->toIso8601String()]),
        ], ['name' => 'Later']))->assertCreated();
        actingAs($admin)->postJson(route('api.v1.admin.sale-events.store'), saleEventPayload([saleEventItemPayload($variant, 'percent', 10)], ['name' => 'Now']))->assertCreated();

        expect($variant->fresh()?->current_price)->toBe(90_000);

        $this->travel(25)->hours();
        $this->artisan('prices:refresh --due')->assertSuccessful();

        expect($variant->fresh()?->current_price)->toBe(50_000);
    });

    it('refuses an outdated order', function () {
        $first = SaleEvent::factory()->create();
        SaleEvent::factory()->create();

        actingAs(saleEventManager())->putJson(route('api.v1.admin.sale-events.order'), ['ids' => [$first->id]])
            ->assertConflict()
            ->assertJsonPath('code', 'sale_event_list_changed');
    });
});

describe('the list, deleting and preview', function () {
    it('lists events in priority order with counts and filters by status', function () {
        $variant = eventVariant();
        $admin = saleEventManager();
        actingAs($admin)->postJson(route('api.v1.admin.sale-events.store'), saleEventPayload([saleEventItemPayload($variant)], ['name' => 'Running']))->assertCreated();
        actingAs($admin)->postJson(route('api.v1.admin.sale-events.store'), saleEventPayload(overrides: [
            'name' => 'Next week',
            'starts_at' => now()->addWeek()->toIso8601String(),
            'ends_at' => now()->addWeeks(2)->toIso8601String(),
        ]))->assertCreated();

        actingAs($admin)->getJson(route('api.v1.admin.sale-events.index'))
            ->assertOk()
            ->assertJsonPath('data.*.name', ['Running', 'Next week'])
            ->assertJsonPath('data.0.items_count', 1)
            ->assertJsonPath('data.0.products_count', 1);

        actingAs($admin)->getJson(route('api.v1.admin.sale-events.index', ['status' => 'scheduled']))
            ->assertJsonPath('data.*.name', ['Next week']);
    });

    it('deletes an event for good and restores its products’ prices', function () {
        $variant = eventVariant(100_000);
        $admin = saleEventManager();
        $id = actingAs($admin)->postJson(route('api.v1.admin.sale-events.store'), saleEventPayload([saleEventItemPayload($variant, 'percent', 20)]))->json('data.id');

        actingAs($admin)->deleteJson(route('api.v1.admin.sale-events.destroy', $id))->assertNoContent();

        expect(SaleEvent::query()->exists())->toBeFalse()
            ->and($variant->fresh()?->current_price)->toBe(100_000)
            ->and($variant->fresh()?->sale_event_id)->toBeNull()
            ->and(Activity::query()->where('event', 'sale_event.deleted')->sole()->properties['name'])->toBe('Yalda night');
    });

    it('gives staff a preview token for the landing page', function () {
        $event = SaleEvent::factory()->create();

        $token = actingAs(saleEventManager())->postJson(route('api.v1.admin.sale-events.preview', $event))
            ->assertCreated()
            ->json('data.token');

        expect(PreviewToken::read($token, PreviewToken::SALE_EVENT)['id'] ?? null)->toBe($event->id)
            ->and(PreviewToken::read($token, PreviewToken::PAGE))->toBeNull();
    });
});

describe('adding products', function () {
    it('returns the enabled variants of the chosen products, in the order chosen', function () {
        $hat = eventVariant(50_000)->product;
        $shoe = eventVariant(200_000)->product;
        $second = ProductVariant::factory()->for($shoe)->create(['price' => 210_000]);
        ProductVariant::factory()->for($shoe)->create(['is_enabled' => false]);
        $deleted = eventVariant()->product;
        $deleted->delete();

        actingAs(saleEventManager())
            ->postJson(route('api.v1.admin.sale-events.variants'), ['product_ids' => [$shoe->id, $hat->id, $deleted->id]])
            ->assertOk()
            ->assertJsonPath('data.*.product.id', [$shoe->id, $shoe->id, $hat->id])
            ->assertJsonPath('data.1.variant.id', $second->id)
            ->assertJsonPath('data.1.variant.price', 210_000)
            ->assertJsonPath('data.2.product.name', $hat->name);
    });

    it('needs the discounts.manage permission and at least one product', function () {
        actingAs(saleEventManager(UserRole::Author))
            ->postJson(route('api.v1.admin.sale-events.variants'), ['product_ids' => [1]])
            ->assertForbidden();

        actingAs(saleEventManager())
            ->postJson(route('api.v1.admin.sale-events.variants'), ['product_ids' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('product_ids');
    });
});

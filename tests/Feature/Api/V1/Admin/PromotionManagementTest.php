<?php

declare(strict_types=1);

use App\Actions\Pricing\RefreshPricesAction;
use App\Enums\DiscountSource;
use App\Enums\UserRole;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\Tag;
use App\Models\User;
use App\Support\Settings;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\getJson;
use function Pest\Laravel\travel;

function promotionManager(UserRole $role = UserRole::Admin): User
{
    return User::factory()->withRole($role)->create();
}

/**
 * A product in a (product) category, with one variant at $price Rials.
 */
function pricedProduct(int $price = 100_000, ?Category $category = null, array $attributes = [], array $variant = []): Product
{
    $product = Product::factory()->published()->create($attributes);
    $product->categories()->attach($category ?? Category::factory()->create(), ['is_primary' => true]);
    ProductVariant::factory()->for($product)->create(['price' => $price, ...$variant]);

    return $product;
}

function variantOf(Product $product): ProductVariant
{
    return ProductVariant::query()->where('product_id', $product->id)->sole();
}

/**
 * @return array<string, mixed>
 */
function promotionPayload(array $overrides = []): array
{
    return [
        'name' => 'Summer sale',
        'type' => 'percent',
        'value' => 20,
        'max_discount' => null,
        'scope' => 'all',
        'product_ids' => [],
        'category_ids' => [],
        'brand_ids' => [],
        'tag_ids' => [],
        'excluded_product_ids' => [],
        'starts_at' => null,
        'ends_at' => null,
        'is_active' => true,
        ...$overrides,
    ];
}

describe('access', function () {
    it('returns 401 to guests and 403 to authors', function () {
        getJson(route('api.v1.admin.promotions.index'))->assertUnauthorized();
        actingAs(promotionManager(UserRole::Author))->getJson(route('api.v1.admin.promotions.index'))->assertForbidden();
        actingAs(promotionManager(UserRole::Author))->postJson(route('api.v1.admin.promotions.store'), promotionPayload())->assertForbidden();
    });
});

describe('saving reprices the catalog', function () {
    it('applies a percentage to every product, rounded down to whole Toman, with an audit entry', function () {
        app(Settings::class)->set([Settings::PRICE_DISPLAY_CURRENCY => 'IRT']);
        $product = pricedProduct(123_456);

        actingAs(promotionManager())->postJson(route('api.v1.admin.promotions.store'), promotionPayload(['value' => 15]))
            ->assertCreated()
            ->assertJsonPath('data.state', 'active');

        // 15% of 123,456 = 18,518.4 → 18,510 Rials (whole Toman).
        expect(variantOf($product)->current_price)->toBe(123_456 - 18_510)
            ->and(variantOf($product)->discount_source)->toBe(DiscountSource::Promotion)
            ->and(Activity::query()->where('event', 'promotion.created')->sole()->properties['name'])->toBe('Summer sale');
    });

    it('caps a percentage with the maximum discount and applies a fixed amount', function () {
        app(Settings::class)->set([Settings::PRICE_DISPLAY_CURRENCY => 'IRR']);
        $expensive = pricedProduct(1_000_000);
        $admin = promotionManager();

        actingAs($admin)->postJson(route('api.v1.admin.promotions.store'), promotionPayload(['value' => 50, 'max_discount' => 100_000]))->assertCreated();
        expect(variantOf($expensive)->current_price)->toBe(900_000);

        Promotion::query()->delete();
        actingAs($admin)->postJson(route('api.v1.admin.promotions.store'), promotionPayload(['type' => 'fixed', 'value' => 70_000]))->assertCreated();
        expect(variantOf($expensive)->current_price)->toBe(930_000);
    });

    it('applies to chosen categories with their sub-categories, brands, tags and products, minus excluded ones', function () {
        $parent = Category::factory()->create();
        $child = Category::factory()->childOf($parent)->create();
        $brand = Brand::factory()->create();
        $tag = Tag::query()->create(['name' => 'Summer', 'slug' => 'summer']);

        $inChild = pricedProduct(100_000, $child);
        $excluded = pricedProduct(100_000, $child);
        $branded = pricedProduct(100_000, attributes: ['brand_id' => $brand->id]);
        $tagged = pricedProduct(100_000);
        $tagged->tags()->attach($tag->id);
        $chosen = pricedProduct(100_000);
        $other = pricedProduct(100_000);

        actingAs(promotionManager())->postJson(route('api.v1.admin.promotions.store'), promotionPayload([
            'scope' => 'selected',
            'category_ids' => [$parent->id],
            'brand_ids' => [$brand->id],
            'tag_ids' => [$tag->id],
            'product_ids' => [$chosen->id],
            'excluded_product_ids' => [$excluded->id],
        ]))->assertCreated();

        expect([$inChild, $branded, $tagged, $chosen, $excluded, $other])
            ->sequence(
                fn ($p) => expect(variantOf($p->value)->current_price)->toBe(80_000),
                fn ($p) => expect(variantOf($p->value)->current_price)->toBe(80_000),
                fn ($p) => expect(variantOf($p->value)->current_price)->toBe(80_000),
                fn ($p) => expect(variantOf($p->value)->current_price)->toBe(80_000),
                fn ($p) => expect(variantOf($p->value)->current_price)->toBe(100_000),
                fn ($p) => expect(variantOf($p->value)->current_price)->toBe(100_000),
            );
    });

    it('keeps the lowest price: a better sale price wins over a promotion, never both', function () {
        $saleWins = pricedProduct(100_000, variant: ['sale_price' => 70_000]);
        $promotionWins = pricedProduct(100_000, variant: ['sale_price' => 95_000]);

        actingAs(promotionManager())->postJson(route('api.v1.admin.promotions.store'), promotionPayload(['value' => 20]))->assertCreated();

        expect(variantOf($saleWins)->current_price)->toBe(70_000)
            ->and(variantOf($saleWins)->discount_source)->toBe(DiscountSource::Sale)
            ->and(variantOf($promotionWins)->current_price)->toBe(80_000);
    });

    it('goes back to the best other price when a promotion is deleted or disabled', function () {
        $product = pricedProduct(100_000, variant: ['sale_price' => 90_000]);
        $admin = promotionManager();
        $id = actingAs($admin)->postJson(route('api.v1.admin.promotions.store'), promotionPayload(['value' => 30]))->json('data.id');
        expect(variantOf($product)->current_price)->toBe(70_000);

        actingAs($admin)->putJson(route('api.v1.admin.promotions.update', $id), promotionPayload(['value' => 30, 'is_active' => false]))->assertOk();
        expect(variantOf($product)->current_price)->toBe(90_000);

        actingAs($admin)->putJson(route('api.v1.admin.promotions.update', $id), promotionPayload(['value' => 30]))->assertOk();
        actingAs($admin)->deleteJson(route('api.v1.admin.promotions.destroy', $id))->assertNoContent();

        expect(variantOf($product)->current_price)->toBe(90_000)
            ->and(variantOf($product)->discount_source)->toBe(DiscountSource::Sale)
            ->and(Activity::query()->where('event', 'promotion.updated')->pluck('properties')->map(fn ($p) => $p['fields'])->all())->toBe([['is_active'], ['is_active']]);
    });

    it('keeps the promotion price when the product is saved again', function () {
        $admin = promotionManager();
        $product = pricedProduct(100_000);
        actingAs($admin)->postJson(route('api.v1.admin.promotions.store'), promotionPayload(['value' => 10]))->assertCreated();
        $variant = variantOf($product);

        $variant->update(['price' => 200_000]);
        app(RefreshPricesAction::class)->handle([$product->id]);

        expect($variant->fresh()?->current_price)->toBe(180_000);
    });
});

describe('scheduling', function () {
    it('starts and ends on time with the scheduled refresh', function () {
        $product = pricedProduct(100_000);

        actingAs(promotionManager())->postJson(route('api.v1.admin.promotions.store'), promotionPayload([
            'starts_at' => now()->addHour()->toIso8601String(),
            'ends_at' => now()->addHours(2)->toIso8601String(),
        ]))->assertCreated()->assertJsonPath('data.state', 'scheduled');

        expect(variantOf($product)->current_price)->toBe(100_000);

        travel(61)->minutes();
        artisan('prices:refresh --due')->assertSuccessful();
        expect(variantOf($product)->current_price)->toBe(80_000);

        travel(60)->minutes();
        artisan('prices:refresh --due')->assertSuccessful();
        expect(variantOf($product)->current_price)->toBe(100_000);
    });

    it('lets a sale end on time too', function () {
        $product = pricedProduct(100_000, variant: ['sale_price' => 60_000, 'sale_ends_at' => now()->addHour()]);
        expect(variantOf($product)->current_price)->toBe(60_000);

        travel(61)->minutes();
        artisan('prices:refresh --due')->assertSuccessful();

        expect(variantOf($product)->current_price)->toBe(100_000);
    });
});

describe('validation and preview', function () {
    it('refuses incomplete or out-of-range promotions', function (array $overrides, string $error) {
        actingAs(promotionManager())->postJson(route('api.v1.admin.promotions.store'), promotionPayload($overrides))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($error);
    })->with([
        'percent over 90' => [['value' => 95], 'value'],
        'max discount on a fixed amount' => [['type' => 'fixed', 'value' => 1000, 'max_discount' => 500], 'max_discount'],
        'selected with no targets' => [['scope' => 'selected'], 'scope'],
        'unknown category' => [['scope' => 'selected', 'category_ids' => [999999]], 'category_ids.0'],
        'end before start' => [['starts_at' => '2030-01-02', 'ends_at' => '2030-01-01'], 'ends_at'],
    ]);

    it('counts the products a promotion would apply to', function () {
        $category = Category::factory()->create();
        pricedProduct(category: $category);
        pricedProduct(category: $category);
        pricedProduct();

        actingAs(promotionManager())->postJson(route('api.v1.admin.promotions.preview'), promotionPayload(['scope' => 'selected', 'category_ids' => [$category->id]]))
            ->assertOk()
            ->assertJsonPath('data.products_count', 2);
    });

    it('lists promotions by status', function () {
        Promotion::factory()->create(['name' => 'Running']);
        Promotion::factory()->create(['name' => 'Later', 'starts_at' => now()->addDay()]);
        Promotion::factory()->create(['name' => 'Over', 'ends_at' => now()->subDay()]);
        Promotion::factory()->create(['name' => 'Off', 'is_active' => false]);

        $names = fn (string $status) => actingAs(promotionManager())->getJson(route('api.v1.admin.promotions.index', ['status' => $status]))->json('data.*.name');

        expect($names('active'))->toBe(['Running'])
            ->and($names('scheduled'))->toBe(['Later'])
            ->and($names('expired'))->toBe(['Over'])
            ->and($names('disabled'))->toBe(['Off']);
    });
});

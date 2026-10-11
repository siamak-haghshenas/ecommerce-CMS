<?php

declare(strict_types=1);

use App\Enums\DiscountSource;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Testing\TestResponse;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\getJson;
use function Pest\Laravel\travel;

beforeEach(function () {
    app(Settings::class)->set([Settings::PRICE_DISPLAY_CURRENCY => 'IRR']);
});

function promotionManager(UserRole $role = UserRole::Admin): User
{
    return User::factory()->withRole($role)->create();
}

/**
 * A published product with one variant at $price Rials and 10 in stock.
 *
 * @param  array<string, mixed>  $product
 * @param  array<string, mixed>  $variant
 */
function promotedVariant(int $price = 200_000, array $product = [], array $variant = []): ProductVariant
{
    $model = Product::factory()->published()->create($product);
    $model->categories()->attach(Category::factory()->create(), ['is_primary' => true]);

    return ProductVariant::factory()->for($model)->create(['price' => $price, 'stock_quantity' => 10, ...$variant]);
}

/**
 * A row as the page sends it: 20% off, running from an hour ago for a week.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function promotionRow(ProductVariant $variant, array $overrides = []): array
{
    return [
        'variant_id' => $variant->id,
        'discount_type' => 'percent',
        'discount_value' => 20,
        'max_per_order' => null,
        'quantity_limit' => null,
        'starts_at' => now()->subHour()->toIso8601String(),
        'ends_at' => now()->addWeek()->toIso8601String(),
        ...$overrides,
    ];
}

/**
 * @param  list<array<string, mixed>>  $rows
 * @param  list<int>  $deleted
 */
function savePromotions(array $rows, array $deleted = [], ?User $actor = null): TestResponse
{
    return actingAs($actor ?? promotionManager())->putJson(route('api.v1.admin.promotions.save'), ['promotions' => $rows, 'deleted' => $deleted]);
}

describe('access', function () {
    it('returns 401 to guests and 403 to authors', function () {
        getJson(route('api.v1.admin.promotions.index'))->assertUnauthorized();
        actingAs(promotionManager(UserRole::Author))->getJson(route('api.v1.admin.promotions.index'))->assertForbidden();
        savePromotions([], actor: promotionManager(UserRole::Author))->assertForbidden();
        actingAs(promotionManager(UserRole::Author))->getJson(route('api.v1.admin.promotions.variants'))->assertForbidden();
    });
});

describe('saving', function () {
    it('creates rows per SKU, reprices them with their order limit and logs each one', function () {
        $percent = promotedVariant(200_000, variant: ['sku' => 'TS-S']);
        $fixed = promotedVariant(80_000);

        savePromotions([
            promotionRow($percent, ['discount_value' => 25, 'max_per_order' => 2, 'quantity_limit' => 50]),
            promotionRow($fixed, ['discount_type' => 'fixed', 'discount_value' => 30_000]),
        ])
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.state', 'active');

        $row = Promotion::query()->where('product_variant_id', $percent->id)->sole();

        expect($percent->fresh()?->current_price)->toBe(150_000)
            ->and($percent->fresh()?->discount_source)->toBe(DiscountSource::Promotion)
            ->and($percent->fresh()?->promotion_id)->toBe($row->id)
            ->and($percent->fresh()?->max_per_order)->toBe(2)
            ->and($fixed->fresh()?->current_price)->toBe(50_000)
            ->and(Activity::query()->where('event', 'promotion.created')->count())->toBe(2)
            ->and(Activity::query()->where('event', 'promotion.created')->where('subject_id', $row->id)->sole()->properties['sku'])->toBe('TS-S');
    });

    it('updates changed rows, deletes removed ones and restores their price', function () {
        $kept = promotedVariant(100_000);
        $removed = promotedVariant(100_000);
        $keptRow = Promotion::factory()->forVariant($kept)->create();
        $removedRow = Promotion::factory()->forVariant($removed)->create();
        artisan('prices:refresh');

        savePromotions([['id' => $keptRow->id, ...promotionRow($kept, ['discount_value' => 50])]], [$removedRow->id])->assertOk();

        expect($kept->fresh()?->current_price)->toBe(50_000)
            ->and($removed->fresh()?->current_price)->toBe(100_000)
            ->and($removed->fresh()?->discount_source)->toBeNull()
            ->and(Promotion::query()->whereKey($removedRow->id)->exists())->toBeFalse()
            ->and(Activity::query()->where('event', 'promotion.updated')->sole()->properties['fields'])->toBe(['discount_value'])
            ->and(Activity::query()->where('event', 'promotion.deleted')->exists())->toBeTrue();
    });

    it('lets a variant have several rows that don’t overlap, each applied in its own dates', function () {
        $variant = promotedVariant(100_000);

        savePromotions([
            promotionRow($variant, ['discount_value' => 10, 'ends_at' => now()->addDay()->toIso8601String()]),
            promotionRow($variant, ['discount_value' => 30, 'starts_at' => now()->addDay()->toIso8601String(), 'ends_at' => now()->addDays(3)->toIso8601String()]),
        ])->assertOk();

        expect($variant->fresh()?->current_price)->toBe(90_000);

        travel(25)->hours();
        artisan('prices:refresh --due');

        expect($variant->fresh()?->current_price)->toBe(70_000);

        travel(3)->days();
        artisan('prices:refresh --due');

        expect($variant->fresh()?->current_price)->toBe(100_000);
    });

    it('rejects invalid rows', function (array $row, string $error) {
        $variant = promotedVariant(100_000);

        savePromotions([promotionRow($variant, $row)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$error]);
    })->with([
        'percent over 90' => [['discount_value' => 91], 'promotions.0.discount_value'],
        'amount not below the price' => [['discount_type' => 'fixed', 'discount_value' => 100_000], 'promotions.0.discount_value'],
        'order limit of zero' => [['max_per_order' => 0], 'promotions.0.max_per_order'],
        'quantity of zero' => [['quantity_limit' => 0], 'promotions.0.quantity_limit'],
        'end before start' => [['ends_at' => now()->subDay()->toIso8601String()], 'promotions.0.ends_at'],
        'no start' => [['starts_at' => null], 'promotions.0.starts_at'],
    ]);

    it('refuses overlapping rows of a variant, unless the saved one is removed in the same save', function () {
        $variant = promotedVariant(100_000);
        $saved = Promotion::factory()->forVariant($variant)->create();

        savePromotions([promotionRow($variant)])->assertJsonValidationErrors(['promotions.0.starts_at']);
        savePromotions([promotionRow($variant), promotionRow(promotedVariant())])
            ->assertJsonValidationErrors(['promotions.0.starts_at'])
            ->assertJsonMissingValidationErrors(['promotions.1.starts_at']);

        $other = promotedVariant();
        savePromotions([promotionRow($other), promotionRow($other)])->assertJsonValidationErrors(['promotions.0.starts_at', 'promotions.1.starts_at']);

        savePromotions([promotionRow($variant)], [$saved->id])->assertOk();
    });

    it('refuses a new row on a deleted variant', function () {
        $variant = promotedVariant();
        $variant->delete();

        savePromotions([promotionRow($variant)])->assertJsonValidationErrors(['promotions.0.variant_id']);
    });
});

describe('pricing', function () {
    it('keeps the lowest price: a lower sale price wins over a promotion', function () {
        $saleWins = promotedVariant(100_000, variant: ['sale_price' => 70_000]);
        $promotionWins = promotedVariant(100_000, variant: ['sale_price' => 95_000]);

        savePromotions([promotionRow($saleWins), promotionRow($promotionWins)])->assertOk();

        expect($saleWins->fresh()?->discount_source)->toBe(DiscountSource::Sale)
            ->and($saleWins->fresh()?->max_per_order)->toBeNull()
            ->and($promotionWins->fresh()?->current_price)->toBe(80_000);
    });

    it('stops a row once its discounted quantity is sold', function () {
        $variant = promotedVariant(100_000);
        Promotion::factory()->forVariant($variant)->create(['quantity_limit' => 5, 'sold_count' => 5]);
        artisan('prices:refresh');

        expect($variant->fresh()?->current_price)->toBe(100_000);
        actingAs(promotionManager())->getJson(route('api.v1.admin.promotions.index'))->assertJsonPath('data.0.state', 'sold_out');
    });

    it('shows storefronts the promotion, the units left and the order limit', function () {
        $variant = promotedVariant(100_000, ['slug' => 'shoe']);
        $row = Promotion::factory()->forVariant($variant)->create(['max_per_order' => 2, 'quantity_limit' => 10, 'sold_count' => 3]);
        artisan('prices:refresh');

        getJson(route('api.v1.products.show', 'shoe'))
            ->assertOk()
            ->assertJsonPath('data.variants.0.price', 80_000)
            ->assertJsonPath('data.variants.0.discount_source', 'promotion')
            ->assertJsonPath('data.variants.0.promotion.ends_at', $row->ends_at->toIso8601String())
            ->assertJsonPath('data.variants.0.promotion.remaining', 7)
            ->assertJsonPath('data.variants.0.max_per_order', 2);
    });
});

describe('adding products', function () {
    it('lists published products with their enabled variants in stock', function () {
        $shirt = promotedVariant(100_000, ['name' => 'Shirt'], ['sku' => 'SH-S']);
        ProductVariant::factory()->for($shirt->product)->create(['sku' => 'SH-M', 'stock_quantity' => 0]);
        ProductVariant::factory()->for($shirt->product)->create(['sku' => 'SH-L', 'stock_quantity' => 5, 'is_enabled' => false]);
        promotedVariant(100_000, ['name' => 'Draft', 'status' => 'draft']);
        Promotion::factory()->forVariant($shirt)->create();

        actingAs(promotionManager())->getJson(route('api.v1.admin.promotions.variants', ['status' => 'published', 'stock' => 'in']))
            ->assertOk()
            ->assertJsonPath('data.*.name', ['Shirt'])
            ->assertJsonPath('data.0.variants.*.sku', ['SH-S'])
            ->assertJsonPath('data.0.variants.0.has_promotion', true);

        actingAs(promotionManager())->getJson(route('api.v1.admin.promotions.variants'))
            ->assertJsonPath('data.*.name', ['Draft', 'Shirt'])
            ->assertJsonPath('data.1.variants.*.sku', ['SH-S', 'SH-M']);
    });
});

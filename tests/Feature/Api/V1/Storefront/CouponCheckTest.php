<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Testing\TestResponse;

use function Pest\Laravel\postJson;

/**
 * An enabled variant of a product the storefront shows.
 */
function shopVariant(int $price = 1_000_000): ProductVariant
{
    $product = Product::factory()->published()->create();
    $product->categories()->attach(Category::factory()->create(), ['is_primary' => true]);

    return ProductVariant::factory()->for($product)->create(['price' => $price]);
}

function shopperToken(?User $customer = null): string
{
    return ($customer ?? User::factory()->withRole(UserRole::Customer)->create())
        ->createToken('storefront', ['customer'])->plainTextToken;
}

/**
 * @param  list<array{0: ProductVariant, 1: int}>  $lines
 */
function checkCoupon(string $code, array $lines, ?string $token = null): TestResponse
{
    return asStorefront($token ?? shopperToken(), 'POST', route('api.v1.coupons.check'), [
        'code' => $code,
        'items' => array_map(fn (array $line): array => ['variant_id' => $line[0]->id, 'quantity' => $line[1]], $lines),
    ]);
}

beforeEach(function () {
    app(Settings::class)->set([Settings::PRICE_DISPLAY_CURRENCY => 'IRR']);
});

it('needs a logged-in customer', function () {
    postJson(route('api.v1.coupons.check'), ['code' => 'YALDA', 'items' => []])->assertUnauthorized();
});

it('returns what a code takes off the cart, matching the code like the admin saved it', function () {
    Coupon::factory()->create(['code' => 'YALDA20', 'value' => 20, 'max_discount' => 300_000, 'name' => 'Internal name']);
    $variant = shopVariant(1_000_000);

    checkCoupon(' yalda۲۰ ', [[$variant, 2]])
        ->assertOk()
        ->assertJsonPath('data.code', 'YALDA20')
        ->assertJsonPath('data.type', 'percent')
        ->assertJsonPath('data.subtotal', 2_000_000)
        ->assertJsonPath('data.uncapped_discount', 400_000)
        ->assertJsonPath('data.discount', 300_000)
        ->assertJsonPath('data.total', 1_700_000)
        ->assertJsonPath('data.lines.0.eligible', true)
        ->assertJsonMissingPath('data.name')
        ->assertJsonMissingPath('data.usage_limit');
});

it('refuses codes that can not be used, with their error code', function (array $attributes, string $code) {
    Coupon::factory()->create(['code' => 'SPRING', ...$attributes]);

    checkCoupon('SPRING', [[shopVariant(), 1]])
        ->assertUnprocessable()
        ->assertJsonPath('code', $code);
})->with([
    'switched off' => [['is_active' => false], 'coupon_inactive'],
    'ended' => [['ends_at' => now()->subMinute()], 'coupon_expired'],
    'not started' => [['starts_at' => now()->addDay()], 'coupon_not_started'],
    'used up' => [['usage_limit' => 5, 'used_count' => 5], 'coupon_limit_reached'],
]);

it('refuses unknown and deleted codes the same way', function () {
    Coupon::factory()->create(['code' => 'OLD'])->delete();

    checkCoupon('OLD', [[shopVariant(), 1]])->assertUnprocessable()->assertJsonPath('code', 'coupon_not_found');
    checkCoupon('NOPE', [[shopVariant(), 1]])->assertUnprocessable()->assertJsonPath('code', 'coupon_not_found');
});

it('lets only chosen customers use a coupon for chosen customers', function () {
    $sara = User::factory()->withRole(UserRole::Customer)->create();
    Coupon::factory()->forSelectedCustomers()->create(['code' => 'VIP'])->customers()->attach($sara);
    $variant = shopVariant();

    checkCoupon('VIP', [[$variant, 1]], shopperToken($sara))->assertOk();
    checkCoupon('VIP', [[$variant, 1]])->assertUnprocessable()->assertJsonPath('code', 'coupon_not_for_customer');
});

it('reports a cart below the minimum and a cart without eligible items', function () {
    $variant = shopVariant(400_000);
    Coupon::factory()->create(['code' => 'BIG', 'min_order_amount' => 500_000]);
    Coupon::factory()->create(['code' => 'NOTHIS'])->excludedProducts()->attach($variant->product_id);

    checkCoupon('BIG', [[$variant, 1]])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'coupon_min_order')
        ->assertJsonPath('min_order_amount', 500_000);
    checkCoupon('BIG', [[$variant, 2]])->assertOk();
    checkCoupon('NOTHIS', [[$variant, 1]])->assertUnprocessable()->assertJsonPath('code', 'coupon_no_eligible_items');
});

it('refuses items the storefront does not show', function () {
    Coupon::factory()->create(['code' => 'SPRING']);
    $draft = ProductVariant::factory()->create();
    $disabled = shopVariant();
    $disabled->update(['is_enabled' => false]);

    checkCoupon('SPRING', [[shopVariant(), 1], [$draft, 1], [$disabled, 1]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['items.1.variant_id', 'items.2.variant_id'])
        ->assertJsonMissingValidationErrors('items.0.variant_id');
});

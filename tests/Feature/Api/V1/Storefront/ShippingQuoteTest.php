<?php

declare(strict_types=1);

use App\Enums\CouponType;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\City;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Province;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Testing\TestResponse;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

/**
 * An enabled variant of a product the storefront shows, with the given shipping data.
 *
 * @param  array<string, int|null>  $shipping  weight_grams, length_mm, width_mm, height_mm
 */
function shippableVariant(int $price = 1_000_000, array $shipping = ['weight_grams' => 500]): ProductVariant
{
    $product = Product::factory()->published()->create($shipping);
    $product->categories()->attach(Category::factory()->create(), ['is_primary' => true]);

    return ProductVariant::factory()->for($product)->create(['price' => $price]);
}

/**
 * @param  list<array{0: ProductVariant, 1: int}>  $lines
 */
function quoteShipping(City $city, array $lines, ?string $coupon = null): TestResponse
{
    $token = User::factory()->withRole(UserRole::Customer)->create()->createToken('storefront', ['customer'])->plainTextToken;

    return asStorefront($token, 'POST', route('api.v1.shipping.quote'), [
        'city_id' => $city->id,
        'items' => array_map(fn (array $line): array => ['variant_id' => $line[0]->id, 'quantity' => $line[1]], $lines),
        'coupon' => $coupon,
    ]);
}

/**
 * A post-like weight method: under 1 kg 450,000, 1–3 kg 600,000, 3–20 kg 900,000 Rials.
 */
function postService(array $attributes = []): ShippingMethod
{
    return ShippingMethod::factory()
        ->withRates([[0, 1000, 450_000], [1000, 3000, 600_000], [3000, 20_000, 900_000]])
        ->create(['name' => 'Post', ...$attributes]);
}

beforeEach(function () {
    app(Settings::class)->set([Settings::PRICE_DISPLAY_CURRENCY => 'IRR']);
    $this->city = City::factory()->create();
});

it('needs a logged-in customer', function () {
    postJson(route('api.v1.shipping.quote'), [])->assertUnauthorized();
});

it('lists the active methods the cart can use, in the admin order, with their fees', function () {
    postService(['position' => 2, 'delivery_days_min' => 3, 'delivery_days_max' => 5]);
    ShippingMethod::factory()->courier(800_000, [[$this->city, null]])->create(['name' => 'Courier', 'position' => 1]);
    postService(['name' => 'Switched off', 'is_active' => false]);
    ShippingMethod::factory()->courier(500_000, [[City::factory()->create(), null]])->create(['name' => 'Elsewhere']);

    quoteShipping($this->city, [[shippableVariant(), 1]])
        ->assertOk()
        ->assertJsonPath('data.methods.*.name', ['Courier', 'Post'])
        ->assertJsonPath('data.methods.1.fee', 450_000)
        ->assertJsonPath('data.methods.1.payable_now', 450_000)
        ->assertJsonPath('data.methods.1.collect_on_delivery', 0)
        ->assertJsonPath('data.methods.1.delivery_days', ['min' => 3, 'max' => 5])
        ->assertJsonPath('data.items_total', 1_000_000);
});

it('picks the weight range by total weight plus packaging, a range including its start', function (int $grams, int $quantity, int $fee) {
    postService(['packaging_weight_grams' => 200]);

    quoteShipping($this->city, [[shippableVariant(shipping: ['weight_grams' => $grams]), $quantity]])
        ->assertJsonPath('data.methods.0.fee', $fee);
})->with([
    'below 1 kg' => [399, 2, 450_000],
    'exactly 1 kg' => [400, 2, 600_000],
    'above 3 kg' => [1000, 3, 900_000],
]);

it('hides a weight method once the order is heavier than its last range', function () {
    postService();

    quoteShipping($this->city, [[shippableVariant(shipping: ['weight_grams' => 10_000]), 2]])
        ->assertJsonPath('data.methods', []);
});

it('uses the store default weight for products without one, and hides weight methods without it', function () {
    postService();
    $variant = shippableVariant(shipping: ['weight_grams' => null]);

    quoteShipping($this->city, [[$variant, 1]])->assertJsonPath('data.methods', []);

    app(Settings::class)->set([Settings::DEFAULT_WEIGHT_GRAMS => 1500]);

    quoteShipping($this->city, [[$variant, 1]])->assertJsonPath('data.methods.0.fee', 600_000);
});

it('prices volume methods by the products’ dimensions and hides them when one is missing', function () {
    ShippingMethod::factory()->volume()->withRates([[0, 10_000, 950_000], [10_000, 40_000, 1_400_000]])->create();
    // 350 × 220 × 130 mm = 10,010 cm³.
    $box = shippableVariant(shipping: ['length_mm' => 350, 'width_mm' => 220, 'height_mm' => 130]);

    quoteShipping($this->city, [[$box, 1]])->assertJsonPath('data.methods.0.fee', 1_400_000);
    quoteShipping($this->city, [[$box, 1], [shippableVariant(shipping: ['length_mm' => 100, 'width_mm' => null, 'height_mm' => 10]), 1]])
        ->assertJsonPath('data.methods', []);
});

it('charges a courier city its own fee, others the default fee', function () {
    $karaj = City::factory()->create();
    ShippingMethod::factory()->courier(800_000, [[$this->city, null], [$karaj, 1_200_000]])->create();
    $variant = shippableVariant();

    quoteShipping($this->city, [[$variant, 1]])->assertJsonPath('data.methods.0.fee', 800_000);
    quoteShipping($karaj, [[$variant, 1]])->assertJsonPath('data.methods.0.fee', 1_200_000);
});

it('lets the carrier collect the fee on delivery, outside the online payment', function () {
    postService(['fee_on_delivery' => true]);

    quoteShipping($this->city, [[shippableVariant(), 1]])
        ->assertJsonPath('data.methods.0.fee', 450_000)
        ->assertJsonPath('data.methods.0.fee_on_delivery', true)
        ->assertJsonPath('data.methods.0.payable_now', 0)
        ->assertJsonPath('data.methods.0.collect_on_delivery', 450_000);
});

it('hides methods below their minimum order', function () {
    postService(['min_order_amount' => 2_000_000]);

    quoteShipping($this->city, [[shippableVariant(1_500_000), 1]])->assertJsonPath('data.methods', []);
    quoteShipping($this->city, [[shippableVariant(2_000_000), 1]])->assertJsonPath('data.methods.0.fee', 450_000);
});

it('ships free over an amount, counted after the coupon discount', function () {
    postService(['free_over_amount' => 2_000_000]);
    Coupon::factory()->create(['code' => 'TENOFF', 'type' => CouponType::Fixed, 'value' => 100_000]);
    $variant = shippableVariant(2_000_000);

    quoteShipping($this->city, [[$variant, 1]])
        ->assertJsonPath('data.methods.0.fee', 0)
        ->assertJsonPath('data.methods.0.original_fee', 450_000)
        ->assertJsonPath('data.methods.0.free_reason', 'free_over');

    quoteShipping($this->city, [[$variant, 1]], 'tenoff')
        ->assertJsonPath('data.items_total', 1_900_000)
        ->assertJsonPath('data.methods.0.fee', 450_000)
        ->assertJsonPath('data.methods.0.remaining_for_free', 100_000);
});

it('makes every fee 0 with a free-shipping coupon, leaving nothing to collect on delivery', function () {
    postService(['fee_on_delivery' => true]);
    Coupon::factory()->create(['code' => 'FREESHIP', 'type' => CouponType::FreeShipping, 'value' => null]);

    quoteShipping($this->city, [[shippableVariant(), 1]], 'FREESHIP')
        ->assertJsonPath('data.methods.0.fee', 0)
        ->assertJsonPath('data.methods.0.free_reason', 'coupon')
        ->assertJsonPath('data.methods.0.collect_on_delivery', 0);
});

it('refuses a coupon the customer can not use, with its error code', function () {
    postService();

    quoteShipping($this->city, [[shippableVariant(), 1]], 'NOPE')
        ->assertUnprocessable()
        ->assertJsonPath('code', 'coupon_not_found');
});

it('refuses unknown cities and items the storefront does not sell', function () {
    $hidden = ProductVariant::factory()->for(Product::factory()->create())->create();

    quoteShipping(City::factory()->make(['id' => 999_999]), [[$hidden, 1]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['city_id']);

    quoteShipping($this->city, [[$hidden, 1]])
        ->assertJsonValidationErrors(['items.0.variant_id' => __('coupons.errors.item_unavailable')]);
});

it('lists provinces with their cities for address forms', function () {
    $province = Province::factory()->create(['name' => 'تهران']);
    City::factory()->for($province)->create(['name' => 'تهران']);

    getJson(route('api.v1.provinces.index'))
        ->assertOk()
        ->assertJsonFragment(['name' => 'تهران', 'cities' => [['id' => $province->cities()->first()?->id, 'name' => 'تهران']]]);
});

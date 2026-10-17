<?php

declare(strict_types=1);

use App\Enums\CouponType;
use App\Enums\ShippingMethodType;
use App\Enums\UserRole;
use App\Models\City;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Support\Settings;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

function shippingManager(UserRole $role = UserRole::Admin): User
{
    return User::factory()->withRole($role)->create();
}

/**
 * The save payload the shipping editor sends: a weight method by default.
 *
 * @return array<string, mixed>
 */
function shippingPayload(array $overrides = []): array
{
    return [
        'name' => 'Post',
        'description' => 'Tracked parcel post',
        'type' => 'weight',
        'fee_on_delivery' => false,
        'default_fee' => null,
        'packaging_weight_grams' => 200,
        'min_order_amount' => null,
        'free_over_amount' => 20_000_000,
        'delivery_days_min' => 3,
        'delivery_days_max' => 5,
        'is_active' => true,
        'rates' => [
            ['start' => 0, 'end' => 1000, 'fee' => 450_000],
            ['start' => 1000, 'end' => null, 'fee' => 600_000],
        ],
        'cities' => null,
        ...$overrides,
    ];
}

/**
 * @param  list<array{id: int, fee: int|null}>  $cities
 * @return array<string, mixed>
 */
function courierPayload(array $cities, array $overrides = []): array
{
    return shippingPayload([
        'name' => 'Courier',
        'type' => 'courier',
        'default_fee' => 800_000,
        'packaging_weight_grams' => null,
        'rates' => null,
        'cities' => $cities,
        ...$overrides,
    ]);
}

describe('access', function () {
    it('lets admins manage shipping and keeps authors out', function () {
        getJson(route('api.v1.admin.shipping-methods.index'))->assertUnauthorized();
        actingAs(shippingManager(UserRole::Author))->getJson(route('api.v1.admin.shipping-methods.index'))->assertForbidden();
        actingAs(shippingManager(UserRole::Author))->postJson(route('api.v1.admin.shipping-methods.store'), shippingPayload())->assertForbidden();
    });
});

describe('saving', function () {
    it('creates a weight method with its ranges, last in the checkout order', function () {
        ShippingMethod::factory()->create(['position' => 4]);
        $admin = shippingManager();

        $id = actingAs($admin)->postJson(route('api.v1.admin.shipping-methods.store'), shippingPayload())
            ->assertCreated()
            ->assertJsonPath('data.type', 'weight')
            ->assertJsonPath('data.position', 5)
            ->assertJsonPath('data.rates', [
                ['start' => 0, 'end' => 1000, 'fee' => 450_000],
                ['start' => 1000, 'end' => null, 'fee' => 600_000],
            ])
            ->json('data.id');

        expect(Activity::query()->where('event', 'shipping_method.created')->sole())
            ->subject_id->toBe($id)
            ->causer_id->toBe($admin->id)
            ->properties->toArray()->toBe(['name' => 'Post']);
    });

    it('saves a courier with its cities, each with its own fee or the default', function () {
        [$tehran, $karaj] = City::factory()->count(2)->create();

        actingAs(shippingManager())->postJson(route('api.v1.admin.shipping-methods.store'), courierPayload([
            ['id' => $tehran->id, 'fee' => null],
            ['id' => $karaj->id, 'fee' => 1_200_000],
        ], ['fee_on_delivery' => true]))
            ->assertCreated()
            ->assertJsonPath('data.fee_on_delivery', true)
            ->assertJsonPath('data.default_fee', 800_000)
            ->assertJsonCount(2, 'data.cities')
            ->assertJsonFragment(['id' => $karaj->id, 'name' => $karaj->name, 'fee' => 1_200_000]);
    });

    it('clears what belonged to the old type when the type changes, and logs the changed parts', function () {
        $method = ShippingMethod::factory()->withRates([[0, null, 450_000]])->create(['packaging_weight_grams' => 200]);
        $city = City::factory()->create();

        actingAs(shippingManager())->putJson(route('api.v1.admin.shipping-methods.update', $method), courierPayload([['id' => $city->id, 'fee' => null]]))
            ->assertOk()
            ->assertJsonPath('data.rates', [])
            ->assertJsonPath('data.packaging_weight_grams', null);

        expect($method->fresh())->type->toBe(ShippingMethodType::Courier)
            ->and($method->rates()->count())->toBe(0)
            ->and(Activity::query()->where('event', 'shipping_method.updated')->sole()->properties['fields'])
            ->toContain('type', 'default_fee', 'packaging_weight_grams', 'rates', 'cities');
    });

    it('logs nothing when a save changes nothing', function () {
        $admin = shippingManager();
        $id = actingAs($admin)->postJson(route('api.v1.admin.shipping-methods.store'), shippingPayload())->json('data.id');

        actingAs($admin)->putJson(route('api.v1.admin.shipping-methods.update', $id), shippingPayload())->assertOk();

        expect(Activity::query()->where('event', 'shipping_method.updated')->exists())->toBeFalse();
    });
});

describe('validation', function () {
    it('needs the basics', function () {
        actingAs(shippingManager())->postJson(route('api.v1.admin.shipping-methods.store'), [])
            ->assertJsonValidationErrors(['name', 'type']);
    });

    it('refuses ranges with gaps, overlaps or a missing end', function (array $rates, string $field, string $message) {
        actingAs(shippingManager())->postJson(route('api.v1.admin.shipping-methods.store'), shippingPayload(['rates' => $rates]))
            ->assertJsonValidationErrors([$field => __($message)]);
    })->with([
        'not starting at 0' => [[['start' => 100, 'end' => null, 'fee' => 1]], 'rates.0.start', 'shipping.errors.first_range_start'],
        'a gap' => [[['start' => 0, 'end' => 1000, 'fee' => 1], ['start' => 1500, 'end' => null, 'fee' => 2]], 'rates.1.start', 'shipping.errors.range_gap'],
        'an overlap' => [[['start' => 0, 'end' => 1000, 'fee' => 1], ['start' => 800, 'end' => null, 'fee' => 2]], 'rates.1.start', 'shipping.errors.range_gap'],
        'no end before the last' => [[['start' => 0, 'end' => null, 'fee' => 1], ['start' => 1000, 'end' => null, 'fee' => 2]], 'rates.0.end', 'shipping.errors.range_end_required'],
        'end not above start' => [[['start' => 0, 'end' => 0, 'fee' => 1]], 'rates.0.end', 'shipping.errors.range_end_after_start'],
    ]);

    it('refuses fields of another type', function () {
        $city = City::factory()->create();

        actingAs(shippingManager())->postJson(route('api.v1.admin.shipping-methods.store'), shippingPayload(['cities' => [['id' => $city->id, 'fee' => null]], 'default_fee' => 5]))
            ->assertJsonValidationErrors(['cities', 'default_fee']);

        actingAs(shippingManager())->postJson(route('api.v1.admin.shipping-methods.store'), courierPayload([['id' => $city->id, 'fee' => null]], [
            'rates' => [['start' => 0, 'end' => null, 'fee' => 1]],
            'packaging_weight_grams' => 100,
        ]))->assertJsonValidationErrors(['rates', 'packaging_weight_grams']);
    });

    it('needs ranges for weight methods and cities for couriers', function () {
        actingAs(shippingManager())->postJson(route('api.v1.admin.shipping-methods.store'), shippingPayload(['rates' => []]))
            ->assertJsonValidationErrors(['rates']);
        actingAs(shippingManager())->postJson(route('api.v1.admin.shipping-methods.store'), courierPayload([], ['default_fee' => null]))
            ->assertJsonValidationErrors(['cities', 'default_fee']);
    });

    it('refuses a longest delivery time shorter than the shortest', function () {
        actingAs(shippingManager())->postJson(route('api.v1.admin.shipping-methods.store'), shippingPayload(['delivery_days_min' => 5, 'delivery_days_max' => 3]))
            ->assertJsonValidationErrors(['delivery_days_max' => __('shipping.errors.days_order')]);
    });
});

describe('list, order and delete', function () {
    it('lists every method in the checkout order', function () {
        ShippingMethod::factory()->create(['name' => 'Second', 'position' => 1]);
        ShippingMethod::factory()->create(['name' => 'First', 'position' => 0]);

        actingAs(shippingManager())->getJson(route('api.v1.admin.shipping-methods.index'))
            ->assertOk()
            ->assertJsonPath('data.*.name', ['First', 'Second']);
    });

    it('saves a new order of every method, and refuses an outdated list', function () {
        [$a, $b, $c] = ShippingMethod::factory()->count(3)->sequence(fn ($s) => ['position' => $s->index])->create();
        $admin = shippingManager();

        actingAs($admin)->putJson(route('api.v1.admin.shipping-methods.order'), ['ids' => [$c->id, $a->id, $b->id]])
            ->assertOk()
            ->assertJsonPath('data.*.id', [$c->id, $a->id, $b->id]);

        actingAs($admin)->putJson(route('api.v1.admin.shipping-methods.order'), ['ids' => [$a->id, $b->id]])
            ->assertConflict()
            ->assertJsonPath('code', 'shipping_method_list_changed');
    });

    it('deletes a method softly, so orders can keep pointing at it', function () {
        $method = ShippingMethod::factory()->create(['name' => 'Old post']);

        actingAs(shippingManager())->deleteJson(route('api.v1.admin.shipping-methods.destroy', $method))->assertNoContent();

        expect($method->fresh()?->trashed())->toBeTrue()
            ->and(Activity::query()->where('event', 'shipping_method.deleted')->sole()->properties['name'])->toBe('Old post');
        actingAs(shippingManager())->getJson(route('api.v1.admin.shipping-methods.show', $method))->assertNotFound();
    });

    it('counts the products shipping can not measure, and the product list filters them', function () {
        Product::factory()->create(['weight_grams' => 500, 'length_mm' => 100, 'width_mm' => 100, 'height_mm' => 100]);
        $noWeight = Product::factory()->create(['weight_grams' => null, 'length_mm' => 100, 'width_mm' => 100, 'height_mm' => 100]);
        $flat = Product::factory()->create(['weight_grams' => 500, 'length_mm' => 100, 'width_mm' => 0, 'height_mm' => 100]);
        Product::factory()->create(['weight_grams' => null])->delete();
        $admin = shippingManager();

        actingAs($admin)->getJson(route('api.v1.admin.shipping-methods.index'))
            ->assertJsonPath('meta.unmeasured_products', ['missing_weight' => 1, 'missing_dimensions' => 1]);

        actingAs($admin)->getJson(route('api.v1.admin.products.index', ['shipping' => 'missing_weight']))
            ->assertJsonPath('data.*.id', [$noWeight->id]);
        actingAs($admin)->getJson(route('api.v1.admin.products.index', ['shipping' => 'missing_dimensions']))
            ->assertJsonPath('data.*.id', [$flat->id]);
    });

    it('lists provinces with their cities for the city picker', function () {
        $city = City::factory()->create();

        actingAs(shippingManager())->getJson(route('api.v1.admin.provinces.index'))
            ->assertOk()
            ->assertJsonPath('data.0.cities.0.id', $city->id);
    });
});

describe('test box', function () {
    beforeEach(function () {
        app(Settings::class)->set([Settings::PRICE_DISPLAY_CURRENCY => 'IRR', Settings::DEFAULT_WEIGHT_GRAMS => 500]);
    });

    it('prices the unsaved method for sample items, even while it is switched off', function () {
        $weighed = ProductVariant::factory()->for(Product::factory()->create(['weight_grams' => 850]))->create(['price' => 1_000_000]);
        $unweighed = ProductVariant::factory()->for(Product::factory()->create(['weight_grams' => null]))->create(['price' => 500_000]);

        actingAs(shippingManager())->postJson(route('api.v1.admin.shipping-methods.simulate'), [
            'method' => shippingPayload(['is_active' => false]),
            'items' => [['variant_id' => $weighed->id, 'quantity' => 1], ['variant_id' => $unweighed->id, 'quantity' => 1]],
        ])
            ->assertOk()
            ->assertJsonPath('data.lines.1.uses_default_weight', true)
            ->assertJsonPath('data.weight_grams', 1350)
            ->assertJsonPath('data.shipping.measure', 1550)
            ->assertJsonPath('data.shipping.range', ['start' => 1000, 'end' => null, 'fee' => 600_000])
            ->assertJsonPath('data.shipping.fee', 600_000)
            ->assertJsonPath('data.shipping.remaining_for_free', 18_500_000);
    });

    it('says why the method is not offered', function () {
        $variant = ProductVariant::factory()->create();

        actingAs(shippingManager())->postJson(route('api.v1.admin.shipping-methods.simulate'), [
            'method' => courierPayload([['id' => City::factory()->create()->id, 'fee' => null]]),
            'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
            'city_id' => City::factory()->create()->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.shipping.available', false)
            ->assertJsonPath('data.shipping.reason', 'city_not_served');
    });

    it('applies a free-shipping coupon by its code', function () {
        Coupon::factory()->create(['code' => 'FREESHIP', 'type' => CouponType::FreeShipping, 'value' => null, 'is_active' => false]);
        $variant = ProductVariant::factory()->create();

        actingAs(shippingManager())->postJson(route('api.v1.admin.shipping-methods.simulate'), [
            'method' => shippingPayload(),
            'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
            'coupon_code' => 'freeship',
        ])
            ->assertOk()
            ->assertJsonPath('data.free_shipping_coupon', true)
            ->assertJsonPath('data.shipping.fee', 0)
            ->assertJsonPath('data.shipping.free_reason', 'coupon');
    });
});

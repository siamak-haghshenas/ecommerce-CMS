<?php

declare(strict_types=1);

use App\Enums\CouponCustomerScope;
use App\Enums\UserRole;
use App\Models\Coupon;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

function couponManager(UserRole $role = UserRole::Admin): User
{
    return User::factory()->withRole($role)->create();
}

/**
 * The save payload the coupon editor sends.
 *
 * @return array<string, mixed>
 */
function couponPayload(array $overrides = []): array
{
    return [
        'code' => 'YALDA20',
        'name' => 'Yalda',
        'type' => 'percent',
        'value' => 20,
        'max_discount' => 1_500_000,
        'min_order_amount' => null,
        'applies_to' => 'all',
        'category_ids' => [],
        'brand_ids' => [],
        'tag_ids' => [],
        'product_ids' => [],
        'excluded_product_ids' => [],
        'exclude_discounted' => false,
        'customer_scope' => 'all',
        'customer_ids' => [],
        'usage_limit' => 100,
        'per_customer_limit' => 1,
        'first_order_only' => false,
        'starts_at' => now()->subHour()->toIso8601String(),
        'ends_at' => now()->addDays(5)->toIso8601String(),
        'is_active' => true,
        ...$overrides,
    ];
}

describe('access', function () {
    it('lets admins manage coupons and keeps authors out', function () {
        getJson(route('api.v1.admin.coupons.index'))->assertUnauthorized();
        actingAs(couponManager(UserRole::Author))->getJson(route('api.v1.admin.coupons.index'))->assertForbidden();
        actingAs(couponManager(UserRole::Author))->postJson(route('api.v1.admin.coupons.store'), couponPayload())->assertForbidden();
    });
});

describe('saving', function () {
    it('creates a coupon limited to chosen customers and returns them', function () {
        $sara = User::factory()->withRole(UserRole::Customer)->create(['name' => 'Sara']);
        $reza = User::factory()->withRole(UserRole::Customer)->create(['name' => 'Reza']);

        $response = actingAs(couponManager())
            ->postJson(route('api.v1.admin.coupons.store'), couponPayload([
                'code' => ' yalda۲۰ ',
                'customer_scope' => 'selected',
                'customer_ids' => [$sara->id, $reza->id],
            ]))
            ->assertCreated()
            ->assertJsonPath('data.code', 'YALDA20')
            ->assertJsonPath('data.state', 'active')
            ->assertJsonPath('data.customer_scope', 'selected')
            ->assertJsonPath('data.customers_count', 2)
            ->assertJsonPath('data.customers.0.name', 'Reza')
            ->assertJsonPath('data.customers.1.phone_number', $sara->phone_number);

        $coupon = Coupon::query()->findOrFail($response->json('data.id'));

        expect($coupon->isFor($sara))->toBeTrue()
            ->and($coupon->isFor(User::factory()->withRole(UserRole::Customer)->create()))->toBeFalse()
            ->and(Activity::query()->where('event', 'coupon.created')->value('properties')->toArray())->toBe(['code' => 'YALDA20']);
    });

    it('switches to every customer and empties the list, logging only the changed fields', function () {
        $coupon = Coupon::factory()->forSelectedCustomers()->create(['code' => 'VIP50']);
        $coupon->customers()->attach(User::factory()->withRole(UserRole::Customer)->create());

        actingAs(couponManager())
            ->putJson(route('api.v1.admin.coupons.update', $coupon), couponPayload(['code' => 'VIP50', 'customer_scope' => 'all', 'customer_ids' => []]))
            ->assertOk()
            ->assertJsonPath('data.customer_scope', 'all')
            ->assertJsonCount(0, 'data.customers');

        $fields = Activity::query()->where('event', 'coupon.updated')->sole()->properties['fields'];

        expect($coupon->customers()->count())->toBe(0)
            ->and($fields)->toContain('customers')
            ->and($coupon->fresh()->customer_scope)->toBe(CouponCustomerScope::All);
    });

    it('needs a customer for a chosen-customers coupon and refuses staff accounts', function () {
        $staffMember = couponManager();

        actingAs(couponManager())
            ->postJson(route('api.v1.admin.coupons.store'), couponPayload(['customer_scope' => 'selected', 'customer_ids' => []]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('customer_ids');

        actingAs(couponManager())
            ->postJson(route('api.v1.admin.coupons.store'), couponPayload(['customer_scope' => 'selected', 'customer_ids' => [$staffMember->id]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('customer_ids.0');
    });

    it('checks the code, the percentage and the dates', function (array $overrides, string $field) {
        Coupon::factory()->create(['code' => 'TAKEN1']);

        actingAs(couponManager())
            ->postJson(route('api.v1.admin.coupons.store'), couponPayload($overrides))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    })->with([
        'a code someone has' => [['code' => 'taken1'], 'code'],
        'letters other than Latin' => [['code' => 'یلدا20'], 'code'],
        'a percentage above 90' => [['value' => 91], 'value'],
        'no amount for a fixed coupon' => [['type' => 'fixed', 'value' => null], 'value'],
        'an end before the start' => [['ends_at' => now()->subDay()->toIso8601String()], 'ends_at'],
        'chosen products (not available yet)' => [['applies_to' => 'selected'], 'applies_to'],
    ]);

    it('saves free shipping without an amount or cap', function () {
        actingAs(couponManager())
            ->postJson(route('api.v1.admin.coupons.store'), couponPayload(['type' => 'free_shipping', 'value' => 50]))
            ->assertCreated()
            ->assertJsonPath('data.value', null)
            ->assertJsonPath('data.max_discount', null);
    });
});

describe('list', function () {
    it('searches and counts each state', function () {
        Coupon::factory()->create(['code' => 'SUMMER10']);
        Coupon::factory()->create(['code' => 'SUMMER20', 'is_active' => false]);
        Coupon::factory()->create(['code' => 'SUMMER30', 'starts_at' => now()->addDay()]);
        Coupon::factory()->create(['code' => 'SUMMER40', 'usage_limit' => 5, 'used_count' => 5]);
        Coupon::factory()->create(['code' => 'SUMMER50', 'ends_at' => now()->subMinute()]);
        Coupon::factory()->create(['code' => 'WINTER10']);

        actingAs(couponManager())
            ->getJson(route('api.v1.admin.coupons.index', ['q' => 'summer', 'status' => 'used_up']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'SUMMER40')
            ->assertJsonPath('meta.status_counts', [
                'all' => 5, 'active' => 1, 'scheduled' => 1, 'used_up' => 1, 'expired' => 1, 'disabled' => 1,
            ]);
    });
});

describe('deleting', function () {
    it('deletes an unused coupon and refuses a used one', function () {
        $unused = Coupon::factory()->create();
        $used = Coupon::factory()->create(['used_count' => 3]);

        actingAs(couponManager())->deleteJson(route('api.v1.admin.coupons.destroy', $unused))->assertNoContent();
        actingAs(couponManager())->deleteJson(route('api.v1.admin.coupons.destroy', $used))
            ->assertConflict()
            ->assertJsonPath('code', 'coupon_in_use');

        expect($unused->fresh()->trashed())->toBeTrue()
            ->and($used->fresh()->trashed())->toBeFalse();
    });
});

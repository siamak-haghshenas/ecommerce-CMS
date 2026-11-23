<?php

declare(strict_types=1);

use App\Actions\Customers\AnonymiseCustomerAction;
use App\Enums\ProductStatus;
use App\Enums\UserRole;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\City;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Testing\TestResponse;

use function Pest\Laravel\artisan;
use function Pest\Laravel\travel;

/**
 * An enabled variant of a product the storefront shows, with its stock.
 *
 * @param  array<string, mixed>  $product  product attributes (track_stock, allow_backorders, ...)
 */
function cartVariant(int $price = 1_000_000, int $stock = 10, array $product = []): ProductVariant
{
    $owner = Product::factory()->published()->create($product);
    $owner->categories()->attach(Category::factory()->create(), ['is_primary' => true]);
    $variant = ProductVariant::factory()->for($owner)->create(['price' => $price]);
    $variant->forceFill(['stock_quantity' => $stock])->save();

    return $variant;
}

function cartCustomer(): User
{
    return User::factory()->withRole(UserRole::Customer)->create();
}

function cartToken(User $customer): string
{
    return $customer->createToken('storefront', ['customer'])->plainTextToken;
}

/**
 * Call the cart like a storefront for a guest: no cookies, only the cart token (if any).
 *
 * @param  array<string, mixed>  $data
 */
function asGuest(?string $cartToken, string $method, string $uri, array $data = []): TestResponse
{
    app('auth')->forgetGuards();
    $request = test()->flushHeaders();

    if ($cartToken !== null) {
        $request->withHeader(Cart::TOKEN_HEADER, $cartToken);
    }

    return $request->json($method, $uri, $data);
}

/**
 * A guest adds a variant; returns the guest cart's token.
 */
function guestAdds(ProductVariant $variant, int $quantity = 1, ?string $cartToken = null): string
{
    $response = asGuest($cartToken, 'POST', route('api.v1.cart.items.store'), ['variant_id' => $variant->id, 'quantity' => $quantity])->assertOk();

    return $cartToken ?? $response->json('meta.cart_token');
}

beforeEach(function () {
    app(Settings::class)->set([Settings::PRICE_DISPLAY_CURRENCY => 'IRR']);
});

describe('guests', function () {
    it('get an empty cart without opening one', function () {
        asGuest(null, 'GET', route('api.v1.cart.show'))
            ->assertOk()
            ->assertJsonPath('data.lines', [])
            ->assertJsonPath('data.can_checkout', false)
            ->assertJsonPath('meta.cart_token', null)
            ->assertJsonPath('meta.currency.display', 'IRR');

        asGuest('unknown-token', 'GET', route('api.v1.cart.show'))->assertOk()->assertJsonPath('data.lines', []);

        expect(Cart::query()->count())->toBe(0);
    });

    it('get a cart token on the first add and keep using that cart', function () {
        $variant = cartVariant(1_000_000);

        $response = asGuest(null, 'POST', route('api.v1.cart.items.store'), ['variant_id' => $variant->id]);
        $token = $response->assertOk()->assertJsonPath('data.items_count', 1)->json('meta.cart_token');
        expect($token)->toBeString()->not->toBeEmpty();

        asGuest($token, 'POST', route('api.v1.cart.items.store'), ['variant_id' => $variant->id, 'quantity' => 2])
            ->assertOk()
            ->assertJsonPath('meta.cart_token', null)
            ->assertJsonPath('data.lines.0.quantity', 3);

        expect(Cart::query()->count())->toBe(1)
            ->and(Cart::query()->first()->token_hash)->toBe(Cart::hashToken($token));
    });

    it('can not use coupons', function () {
        Coupon::factory()->create(['code' => 'YALDA']);
        $token = guestAdds(cartVariant());

        asGuest($token, 'PUT', route('api.v1.cart.coupon.update'), ['code' => 'YALDA'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'cart_login_required');
    });
});

it('keeps a customer cart on their account', function () {
    $customer = cartCustomer();
    $variant = cartVariant();

    asStorefront(cartToken($customer), 'POST', route('api.v1.cart.items.store'), ['variant_id' => $variant->id])
        ->assertOk()
        ->assertJsonPath('meta.cart_token', null);

    expect($customer->cart()->first()->items()->count())->toBe(1);
    asStorefront(cartToken($customer), 'GET', route('api.v1.cart.show'))->assertJsonPath('data.lines.0.variant_id', $variant->id);
});

it('refuses an invalid Bearer token instead of treating it as a guest', function () {
    asStorefront('1|not-a-token', 'GET', route('api.v1.cart.show'))->assertUnauthorized();
});

it('rejects a customer deactivated meanwhile', function () {
    $customer = cartCustomer();
    $token = cartToken($customer);
    $customer->forceFill(['is_active' => false])->save();

    asStorefront($token, 'GET', route('api.v1.cart.show'))->assertForbidden()->assertJsonPath('code', 'account_inactive');
});

it('prices lines at the current price with the savings and totals', function () {
    $sale = cartVariant(1_000_000);
    $sale->update(['sale_price' => 800_000]);
    $regular = cartVariant(500_000);
    $token = guestAdds($sale, 2);
    guestAdds($regular, 1, $token);

    asGuest($token, 'GET', route('api.v1.cart.show'))
        ->assertOk()
        ->assertJsonPath('data.lines.0.unit_price', 800_000)
        ->assertJsonPath('data.lines.0.regular_price', 1_000_000)
        ->assertJsonPath('data.lines.0.discount_percent', 20)
        ->assertJsonPath('data.lines.0.total', 1_600_000)
        ->assertJsonPath('data.lines.0.max_quantity', 10)
        ->assertJsonPath('data.lines.0.problems', [])
        ->assertJsonPath('data.lines.0.product.slug', $sale->product->slug)
        ->assertJsonPath('data.items_count', 3)
        ->assertJsonPath('data.lines_count', 2)
        ->assertJsonPath('data.regular_subtotal', 2_500_000)
        ->assertJsonPath('data.savings', 400_000)
        ->assertJsonPath('data.items_subtotal', 2_100_000)
        ->assertJsonPath('data.total', 2_100_000)
        ->assertJsonPath('data.can_checkout', true)
        ->assertJsonMissingPath('data.lines.0.stock_quantity');
});

describe('adding', function () {
    it('refuses variants the storefront does not sell', function (Closure $hide) {
        $variant = cartVariant();
        $hide($variant);

        asGuest(null, 'POST', route('api.v1.cart.items.store'), ['variant_id' => $variant->id])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'cart_item_unavailable');
    })->with([
        'draft product' => [fn (ProductVariant $variant) => $variant->product->update(['status' => ProductStatus::Draft])],
        'disabled variant' => [fn (ProductVariant $variant) => $variant->update(['is_enabled' => false])],
        'deleted variant' => [fn (ProductVariant $variant) => $variant->delete()],
        'hidden category' => [fn (ProductVariant $variant) => $variant->product->categories()->first()->update(['is_active' => false])],
    ]);

    it('refuses unknown variants', function () {
        asGuest(null, 'POST', route('api.v1.cart.items.store'), ['variant_id' => 999_999])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'cart_item_unavailable');
    });

    it('refuses a variant out of stock, unless stock is untracked or backorders are on', function () {
        asGuest(null, 'POST', route('api.v1.cart.items.store'), ['variant_id' => cartVariant(stock: 0)->id])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'cart_item_out_of_stock');

        asGuest(null, 'POST', route('api.v1.cart.items.store'), ['variant_id' => cartVariant(stock: 0, product: ['allow_backorders' => true])->id])->assertOk();
        asGuest(null, 'POST', route('api.v1.cart.items.store'), ['variant_id' => cartVariant(stock: 0, product: ['track_stock' => false])->id])->assertOk();
    });

    it('keeps a line within the stock, the order limit and the store cap', function () {
        config(['cart.max_quantity_per_line' => 5]);
        $stocked = cartVariant(stock: 3);
        $limited = cartVariant(stock: 50);
        $limited->forceFill(['max_per_order' => 2])->saveQuietly();
        $untracked = cartVariant(stock: 0, product: ['track_stock' => false]);

        $token = guestAdds($stocked, 2);
        asGuest($token, 'POST', route('api.v1.cart.items.store'), ['variant_id' => $stocked->id, 'quantity' => 2])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'cart_quantity_exceeded')
            ->assertJsonPath('max_quantity', 3);

        asGuest($token, 'POST', route('api.v1.cart.items.store'), ['variant_id' => $limited->id, 'quantity' => 3])
            ->assertJsonPath('max_quantity', 2);
        asGuest($token, 'POST', route('api.v1.cart.items.store'), ['variant_id' => $untracked->id, 'quantity' => 6])
            ->assertJsonPath('max_quantity', 5);

        asGuest($token, 'GET', route('api.v1.cart.show'))->assertJsonPath('data.lines.0.quantity', 2);
    });

    it('refuses a new line in a full cart', function () {
        config(['cart.max_lines' => 1]);
        $token = guestAdds(cartVariant());

        asGuest($token, 'POST', route('api.v1.cart.items.store'), ['variant_id' => cartVariant()->id])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'cart_full')
            ->assertJsonPath('max_lines', 1);
    });
});

describe('changing lines', function () {
    it('sets a quantity within the limit, removes a line and empties the cart', function () {
        $variant = cartVariant(stock: 4);
        $other = cartVariant();
        $token = guestAdds($variant);
        guestAdds($other, 1, $token);

        asGuest($token, 'PUT', route('api.v1.cart.items.update', $variant->id), ['quantity' => 4])
            ->assertOk()
            ->assertJsonPath('data.lines.0.quantity', 4);
        asGuest($token, 'PUT', route('api.v1.cart.items.update', $variant->id), ['quantity' => 5])
            ->assertUnprocessable()
            ->assertJsonPath('max_quantity', 4);
        asGuest($token, 'PUT', route('api.v1.cart.items.update', 999_999), ['quantity' => 1])->assertNotFound();

        asGuest($token, 'DELETE', route('api.v1.cart.items.destroy', $other->id))
            ->assertOk()
            ->assertJsonPath('data.lines_count', 1);

        asGuest($token, 'DELETE', route('api.v1.cart.destroy'))->assertOk()->assertJsonPath('data.lines', []);
        expect(Cart::query()->count())->toBe(1);
    });

    it('lets a line above its limit be lowered to it', function () {
        $variant = cartVariant(stock: 5);
        $token = guestAdds($variant, 5);
        $variant->forceFill(['stock_quantity' => 2])->save();

        asGuest($token, 'PUT', route('api.v1.cart.items.update', $variant->id), ['quantity' => 2])
            ->assertOk()
            ->assertJsonPath('data.lines.0.problems', [])
            ->assertJsonPath('data.can_checkout', true);
    });
});

describe('problems on reading', function () {
    it('flags a line above its limit and blocks checkout, without changing it', function () {
        $variant = cartVariant(stock: 5);
        $token = guestAdds($variant, 4);
        $variant->forceFill(['stock_quantity' => 2])->save();

        asGuest($token, 'GET', route('api.v1.cart.show'))
            ->assertJsonPath('data.lines.0.quantity', 4)
            ->assertJsonPath('data.lines.0.max_quantity', 2)
            ->assertJsonPath('data.lines.0.problems', ['exceeds_limit'])
            ->assertJsonPath('data.can_checkout', false);
    });

    it('keeps lines that can no longer be bought out of the totals', function () {
        $gone = cartVariant(1_000_000);
        $empty = cartVariant(300_000, stock: 1);
        $fine = cartVariant(500_000);
        $token = guestAdds($gone);
        guestAdds($empty, 1, $token);
        guestAdds($fine, 1, $token);

        $gone->product->update(['status' => ProductStatus::Archived]);
        $empty->forceFill(['stock_quantity' => 0])->save();

        asGuest($token, 'GET', route('api.v1.cart.show'))
            ->assertJsonPath('data.lines.0.problems', ['unavailable'])
            ->assertJsonPath('data.lines.0.max_quantity', 0)
            ->assertJsonPath('data.lines.0.total', 0)
            ->assertJsonPath('data.lines.1.problems', ['out_of_stock'])
            ->assertJsonPath('data.items_subtotal', 500_000)
            ->assertJsonPath('data.items_count', 3)
            ->assertJsonPath('data.can_checkout', false);
    });

    it('points out a changed price once', function () {
        $variant = cartVariant(1_000_000);
        $token = guestAdds($variant);
        $variant->update(['price' => 1_200_000]);

        asGuest($token, 'GET', route('api.v1.cart.show'))
            ->assertJsonPath('data.lines.0.problems', ['price_changed'])
            ->assertJsonPath('data.lines.0.price_was', 1_000_000)
            ->assertJsonPath('data.lines.0.unit_price', 1_200_000)
            ->assertJsonPath('data.can_checkout', true);

        asGuest($token, 'GET', route('api.v1.cart.show'))
            ->assertJsonPath('data.lines.0.problems', [])
            ->assertJsonPath('data.lines.0.price_was', null);
    });
});

describe('coupons', function () {
    it('applies a code to the customer cart and takes it off the total', function () {
        Coupon::factory()->create(['code' => 'YALDA20', 'value' => 20]);
        $customer = cartCustomer();
        $token = cartToken($customer);
        asStorefront($token, 'POST', route('api.v1.cart.items.store'), ['variant_id' => cartVariant(1_000_000)->id]);

        asStorefront($token, 'PUT', route('api.v1.cart.coupon.update'), ['code' => ' yalda۲۰ '])
            ->assertOk()
            ->assertJsonPath('data.coupon.code', 'YALDA20')
            ->assertJsonPath('data.coupon.discount', 200_000)
            ->assertJsonPath('data.coupon.problem', null)
            ->assertJsonPath('data.total', 800_000);

        asStorefront($token, 'DELETE', route('api.v1.cart.coupon.destroy'))
            ->assertOk()
            ->assertJsonPath('data.coupon', null)
            ->assertJsonPath('data.total', 1_000_000);
    });

    it('does not save a code that can not be used', function () {
        $customer = cartCustomer();
        $token = cartToken($customer);
        asStorefront($token, 'POST', route('api.v1.cart.items.store'), ['variant_id' => cartVariant()->id]);

        asStorefront($token, 'PUT', route('api.v1.cart.coupon.update'), ['code' => 'NOPE'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'coupon_not_found');

        expect($customer->cart()->first()->coupon_code)->toBeNull();
    });

    it('keeps a coupon that stops applying, with the reason, and takes nothing off', function () {
        Coupon::factory()->create(['code' => 'BIG', 'value' => 10, 'min_order_amount' => 1_500_000]);
        $customer = cartCustomer();
        $token = cartToken($customer);
        $first = cartVariant(1_000_000);
        asStorefront($token, 'POST', route('api.v1.cart.items.store'), ['variant_id' => $first->id, 'quantity' => 2]);
        asStorefront($token, 'PUT', route('api.v1.cart.coupon.update'), ['code' => 'BIG'])->assertOk();

        asStorefront($token, 'PUT', route('api.v1.cart.items.update', $first->id), ['quantity' => 1])
            ->assertOk()
            ->assertJsonPath('data.coupon.code', 'BIG')
            ->assertJsonPath('data.coupon.discount', 0)
            ->assertJsonPath('data.coupon.problem.code', 'coupon_min_order')
            ->assertJsonPath('data.coupon.problem.min_order_amount', 1_500_000)
            ->assertJsonPath('data.total', 1_000_000)
            ->assertJsonPath('data.can_checkout', true);
    });
});

describe('merging', function () {
    it('moves the guest cart into the customer cart, adding up quantities', function () {
        $shared = cartVariant(stock: 10);
        $guestOnly = cartVariant();
        $customer = cartCustomer();
        $token = cartToken($customer);
        asStorefront($token, 'POST', route('api.v1.cart.items.store'), ['variant_id' => $shared->id, 'quantity' => 2]);
        $guestToken = guestAdds($shared, 3);
        guestAdds($guestOnly, 1, $guestToken);

        asStorefront($token, 'POST', route('api.v1.cart.merge'), ['cart_token' => $guestToken])
            ->assertOk()
            ->assertJsonPath('data.lines.0.variant_id', $shared->id)
            ->assertJsonPath('data.lines.0.quantity', 5)
            ->assertJsonPath('data.lines.0.problems', [])
            ->assertJsonPath('data.lines.1.variant_id', $guestOnly->id);

        expect(Cart::query()->count())->toBe(1);
        asGuest($guestToken, 'GET', route('api.v1.cart.show'))->assertJsonPath('data.lines', []);

        // Safe to repeat: the token is gone, nothing changes.
        asStorefront($token, 'POST', route('api.v1.cart.merge'), ['cart_token' => $guestToken])->assertOk()->assertJsonPath('data.items_count', 6);
    });

    it('cuts a summed quantity to the limit and says so until the customer changes it', function () {
        $variant = cartVariant(stock: 4);
        $customer = cartCustomer();
        $token = cartToken($customer);
        asStorefront($token, 'POST', route('api.v1.cart.items.store'), ['variant_id' => $variant->id, 'quantity' => 3]);
        $guestToken = guestAdds($variant, 3);

        asStorefront($token, 'POST', route('api.v1.cart.merge'), ['cart_token' => $guestToken])
            ->assertOk()
            ->assertJsonPath('data.lines.0.quantity', 4)
            ->assertJsonPath('data.lines.0.limited_from', 6)
            ->assertJsonPath('data.lines.0.problems', ['exceeds_limit'])
            ->assertJsonPath('data.can_checkout', true);

        asStorefront($token, 'PUT', route('api.v1.cart.items.update', $variant->id), ['quantity' => 4])
            ->assertJsonPath('data.lines.0.limited_from', null)
            ->assertJsonPath('data.lines.0.problems', []);
    });

    it('keeps the customer cart coupon', function () {
        Coupon::factory()->create(['code' => 'MINE']);
        $customer = cartCustomer();
        $token = cartToken($customer);
        asStorefront($token, 'POST', route('api.v1.cart.items.store'), ['variant_id' => cartVariant()->id]);
        asStorefront($token, 'PUT', route('api.v1.cart.coupon.update'), ['code' => 'MINE'])->assertOk();

        asStorefront($token, 'POST', route('api.v1.cart.merge'), ['cart_token' => guestAdds(cartVariant())])
            ->assertJsonPath('data.coupon.code', 'MINE');
    });

    it('needs a logged-in customer', function () {
        asGuest(null, 'POST', route('api.v1.cart.merge'), ['cart_token' => 'x'])->assertUnauthorized();
    });
});

it('quotes shipping for the customer cart and its coupon when no items are sent', function () {
    $city = City::factory()->create();
    ShippingMethod::factory()->withRates([[0, 20_000, 450_000]])->create(['name' => 'Post', 'free_over_amount' => 1_500_000]);
    Coupon::factory()->create(['code' => 'TEN', 'value' => 10]);
    $customer = cartCustomer();
    $token = cartToken($customer);
    asStorefront($token, 'POST', route('api.v1.cart.items.store'), ['variant_id' => cartVariant(1_000_000, product: ['weight_grams' => 500])->id, 'quantity' => 2]);
    asStorefront($token, 'PUT', route('api.v1.cart.coupon.update'), ['code' => 'TEN']);

    asStorefront($token, 'POST', route('api.v1.shipping.quote'), ['city_id' => $city->id])
        ->assertOk()
        ->assertJsonPath('data.items_subtotal', 2_000_000)
        ->assertJsonPath('data.coupon_discount', 200_000)
        ->assertJsonPath('data.methods.0.free_reason', 'free_over');

    asStorefront($token, 'POST', route('api.v1.shipping.quote'), ['city_id' => $city->id, 'coupon' => 'TEN'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('coupon');
});

it('removes carts nobody touched for a while', function () {
    $staleGuest = Cart::factory()->create(['last_activity_at' => now()->minus(days: 31)]);
    $freshGuest = Cart::factory()->create(['last_activity_at' => now()->minus(days: 29)]);
    $customerCart = Cart::factory()->create(['user_id' => cartCustomer()->id, 'token_hash' => null, 'last_activity_at' => now()->minus(days: 31)]);
    $staleCustomer = Cart::factory()->create(['user_id' => cartCustomer()->id, 'token_hash' => null, 'last_activity_at' => now()->minus(days: 91)]);
    CartItem::query()->create(['cart_id' => $staleGuest->id, 'product_variant_id' => cartVariant()->id, 'quantity' => 1, 'unit_price_seen' => 1]);

    artisan('model:prune', ['--model' => [Cart::class]])->assertSuccessful();

    expect(Cart::query()->pluck('id')->all())->toEqualCanonicalizing([$freshGuest->id, $customerCart->id])
        ->and(CartItem::query()->count())->toBe(0);
});

it('keeps a cart in use from being pruned', function () {
    $token = guestAdds(cartVariant());
    travel(29)->days();
    asGuest($token, 'GET', route('api.v1.cart.show'))->assertOk();
    travel(29)->days();

    artisan('model:prune', ['--model' => [Cart::class]])->assertSuccessful();

    expect(Cart::query()->count())->toBe(1);
});

it('deletes the cart of an anonymised customer', function () {
    $customer = cartCustomer();
    asStorefront(cartToken($customer), 'POST', route('api.v1.cart.items.store'), ['variant_id' => cartVariant()->id]);

    app(AnonymiseCustomerAction::class)->handle(User::factory()->withRole(UserRole::SuperAdmin)->create(), $customer);

    expect(Cart::query()->count())->toBe(0)->and(CartItem::query()->count())->toBe(0);
});

<?php

declare(strict_types=1);

use App\Enums\CouponAppliesTo;
use App\Enums\UserRole;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tag;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Testing\TestResponse;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;

/**
 * A product with one variant at $price Rials (and an optional sale price).
 *
 * @param  array<string, mixed>  $attributes
 */
function couponSampleVariant(int $price, array $attributes = [], ?Category $category = null, ?int $salePrice = null): ProductVariant
{
    $product = Product::factory()->published()->create($attributes);

    if ($category !== null) {
        $product->categories()->attach($category, ['is_primary' => true]);
    }

    return ProductVariant::factory()->for($product)->create(['price' => $price, 'sale_price' => $salePrice]);
}

/**
 * The coupon editor's save payload, all eligible-item lists empty.
 *
 * @return array<string, mixed>
 */
function targetedCouponPayload(array $overrides = []): array
{
    return [
        'code' => 'NOROOZ',
        'name' => 'Nowruz',
        'type' => 'percent',
        'value' => 10,
        'max_discount' => null,
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
        'usage_limit' => null,
        'per_customer_limit' => 1,
        'first_order_only' => false,
        'starts_at' => '2026-01-01T00:00:00+00:00',
        'ends_at' => null,
        'is_active' => true,
        ...$overrides,
    ];
}

/**
 * @param  list<array{0: ProductVariant, 1: int}>  $lines
 */
function simulateCoupon(array $coupon, array $lines): TestResponse
{
    return actingAs(User::factory()->withRole(UserRole::Admin)->create())
        ->postJson(route('api.v1.admin.coupons.simulate'), [
            'coupon' => targetedCouponPayload($coupon),
            'items' => array_map(fn (array $line): array => ['variant_id' => $line[0]->id, 'quantity' => $line[1]], $lines),
        ]);
}

beforeEach(function () {
    app(Settings::class)->set([Settings::PRICE_DISPLAY_CURRENCY => 'IRR']);
});

describe('eligible items', function () {
    it('saves chosen categories, brands, tags and products with excluded products, and returns their names', function () {
        $category = Category::factory()->create(['name' => 'Shoes']);
        $brand = Brand::factory()->create(['name' => 'Adidas']);
        $tag = Tag::query()->create(['name' => 'summer', 'slug' => 'summer']);
        $chosen = Product::factory()->create(['name' => 'Cap']);
        $excluded = Product::factory()->create(['name' => 'Gift card']);

        $response = actingAs(User::factory()->withRole(UserRole::Admin)->create())
            ->postJson(route('api.v1.admin.coupons.store'), targetedCouponPayload([
                'applies_to' => 'selected',
                'category_ids' => [$category->id],
                'brand_ids' => [$brand->id],
                'tag_ids' => [$tag->id],
                'product_ids' => [$chosen->id],
                'excluded_product_ids' => [$excluded->id],
            ]))
            ->assertCreated()
            ->assertJsonPath('data.applies_to', 'selected')
            ->assertJsonPath('data.targets.categories', [['id' => $category->id, 'name' => 'Shoes']])
            ->assertJsonPath('data.targets.brands.0.name', 'Adidas')
            ->assertJsonPath('data.targets.tags.0.name', 'summer')
            ->assertJsonPath('data.targets.products.0.name', 'Cap')
            ->assertJsonPath('data.excluded_products', [['id' => $excluded->id, 'name' => 'Gift card']])
            ->assertJsonPath('data.targets_count', ['categories' => 1, 'brands' => 1, 'tags' => 1, 'products' => 1])
            ->assertJsonPath('data.excluded_count', 1);

        $coupon = Coupon::query()->findOrFail($response->json('data.id'));

        actingAs(User::factory()->withRole(UserRole::Admin)->create())
            ->getJson(route('api.v1.admin.coupons.index'))
            ->assertOk()
            ->assertJsonPath('data.0.targets_count.brands', 1)
            ->assertJsonPath('data.0.excluded_count', 1);

        // Back to every product: the chosen lists are emptied, the excluded product stays.
        actingAs(User::factory()->withRole(UserRole::Admin)->create())
            ->putJson(route('api.v1.admin.coupons.update', $coupon), targetedCouponPayload([
                'excluded_product_ids' => [$excluded->id],
            ]))
            ->assertOk()
            ->assertJsonPath('data.targets_count', ['categories' => 0, 'brands' => 0, 'tags' => 0, 'products' => 0])
            ->assertJsonPath('data.excluded_count', 1);

        expect(Activity::query()->where('event', 'coupon.updated')->sole()->properties->get('fields'))
            ->toBe(['applies_to', 'targets']);
    });

    it('refuses chosen items without any, a product both chosen and excluded, and blog categories', function () {
        $product = Product::factory()->create();
        $blogCategory = Category::factory()->blog()->create();
        $admin = User::factory()->withRole(UserRole::Admin)->create();

        actingAs($admin)
            ->postJson(route('api.v1.admin.coupons.store'), targetedCouponPayload(['applies_to' => 'selected']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('applies_to');

        actingAs($admin)
            ->postJson(route('api.v1.admin.coupons.store'), targetedCouponPayload([
                'applies_to' => 'selected',
                'product_ids' => [$product->id],
                'excluded_product_ids' => [$product->id],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('excluded_product_ids.0');

        actingAs($admin)
            ->postJson(route('api.v1.admin.coupons.store'), targetedCouponPayload([
                'applies_to' => 'selected',
                'category_ids' => [$blogCategory->id],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('category_ids.0');

        expect(Coupon::query()->count())->toBe(0);
    });

    it('drops deleted categories, brands and tags from coupons and moves merged tags', function () {
        $category = Category::factory()->create();
        $brand = Brand::factory()->create();
        $gifts = Tag::query()->create(['name' => 'gifts', 'slug' => 'gifts']);
        $gift = Tag::query()->create(['name' => 'gift', 'slug' => 'gift']);
        $coupon = Coupon::factory()->create(['applies_to' => CouponAppliesTo::Selected]);
        $coupon->categories()->attach($category);
        $coupon->brands()->attach($brand);
        $coupon->tags()->attach($gifts);
        $admin = User::factory()->withRole(UserRole::Admin)->create();

        actingAs($admin)->deleteJson(route('api.v1.admin.categories.destroy', $category))->assertNoContent();
        actingAs($admin)->deleteJson(route('api.v1.admin.brands.destroy', $brand))->assertNoContent();
        actingAs($admin)
            ->postJson(route('api.v1.admin.tags.merge', ['tag' => $gifts]), ['into_tag_id' => $gift->id])
            ->assertOk();

        expect($coupon->categories()->count())->toBe(0)
            ->and($coupon->brands()->count())->toBe(0)
            ->and($coupon->tags()->pluck('tags.id')->all())->toBe([$gift->id]);

        actingAs($admin)->deleteJson(route('api.v1.admin.tags.destroy', $gift))->assertNoContent();

        expect($coupon->tags()->count())->toBe(0);
    });
});

describe('sample order', function () {
    it('takes a capped percentage off the eligible lines only', function () {
        $shoes = Category::factory()->create();
        $sneakers = Category::factory()->childOf($shoes)->create();
        $sneaker = couponSampleVariant(2_000_000, category: $sneakers);
        $book = couponSampleVariant(500_000);
        $excluded = couponSampleVariant(1_000_000, category: $shoes);
        $onSale = couponSampleVariant(1_000_000, category: $shoes, salePrice: 800_000);

        simulateCoupon([
            'value' => 20,
            'max_discount' => 500_000,
            'applies_to' => 'selected',
            'category_ids' => [$shoes->id],
            'excluded_product_ids' => [$excluded->product_id],
            'exclude_discounted' => true,
        ], [[$sneaker, 2], [$book, 1], [$excluded, 1], [$onSale, 1]])
            ->assertOk()
            ->assertJsonPath('data.lines.0', ['variant_id' => $sneaker->id, 'quantity' => 2, 'unit_price' => 2_000_000, 'total' => 4_000_000, 'eligible' => true, 'reason' => null])
            ->assertJsonPath('data.lines.1.reason', 'not_targeted')
            ->assertJsonPath('data.lines.2.reason', 'excluded')
            ->assertJsonPath('data.lines.3', ['variant_id' => $onSale->id, 'quantity' => 1, 'unit_price' => 800_000, 'total' => 800_000, 'eligible' => false, 'reason' => 'discounted'])
            ->assertJsonPath('data.subtotal', 6_300_000)
            ->assertJsonPath('data.eligible_subtotal', 4_000_000)
            ->assertJsonPath('data.uncapped_discount', 800_000)
            ->assertJsonPath('data.discount', 500_000)
            ->assertJsonPath('data.problem', null)
            ->assertJsonPath('data.total', 5_800_000);
    });

    it('rounds a percentage down to whole Toman when the store shows Toman', function () {
        app(Settings::class)->set([Settings::PRICE_DISPLAY_CURRENCY => 'IRT']);
        $variant = couponSampleVariant(123_457);

        simulateCoupon(['value' => 10], [[$variant, 1]])
            ->assertOk()
            ->assertJsonPath('data.discount', 12_340);
    });

    it('matches chosen brands, tags and products', function () {
        $brand = Brand::factory()->create();
        $tag = Tag::query()->create(['name' => 'summer', 'slug' => 'summer']);
        $branded = couponSampleVariant(100_000, ['brand_id' => $brand->id]);
        $tagged = couponSampleVariant(100_000);
        $tagged->product->tags()->attach($tag);
        $chosen = couponSampleVariant(100_000);
        $other = couponSampleVariant(100_000);

        simulateCoupon([
            'type' => 'fixed',
            'value' => 1_000_000,
            'applies_to' => 'selected',
            'brand_ids' => [$brand->id],
            'tag_ids' => [$tag->id],
            'product_ids' => [$chosen->product_id],
        ], [[$branded, 1], [$tagged, 1], [$chosen, 1], [$other, 1]])
            ->assertOk()
            ->assertJsonPath('data.lines.*.eligible', [true, true, true, false])
            // A fixed amount never exceeds the eligible lines.
            ->assertJsonPath('data.discount', 300_000)
            ->assertJsonPath('data.total', 100_000);
    });

    it('reports a minimum the eligible lines miss, and free shipping', function () {
        $variant = couponSampleVariant(400_000);

        simulateCoupon(['min_order_amount' => 500_000], [[$variant, 1]])
            ->assertOk()
            ->assertJsonPath('data.problem', 'min_order')
            ->assertJsonPath('data.discount', 0)
            ->assertJsonPath('data.total', 400_000);

        simulateCoupon(['type' => 'free_shipping', 'value' => null, 'min_order_amount' => 500_000], [[$variant, 2]])
            ->assertOk()
            ->assertJsonPath('data.problem', null)
            ->assertJsonPath('data.discount', 0)
            ->assertJsonPath('data.free_shipping', true);

        simulateCoupon(['excluded_product_ids' => [$variant->product_id]], [[$variant, 1]])
            ->assertOk()
            ->assertJsonPath('data.problem', 'no_eligible_items');
    });

    it('needs manage permission and checks the terms', function () {
        $variant = couponSampleVariant(100_000);

        actingAs(User::factory()->withRole(UserRole::Author)->create())
            ->postJson(route('api.v1.admin.coupons.simulate'), ['coupon' => targetedCouponPayload(), 'items' => []])
            ->assertForbidden();

        simulateCoupon(['value' => 95], [[$variant, 1]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('coupon.value');
    });
});

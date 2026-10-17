<?php

declare(strict_types=1);

use App\Actions\Products\RefreshProductSearchTextAction;
use App\Enums\UserRole;
use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Option;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tag;
use App\Models\User;
use App\Support\Settings;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\withHeader;

function staffMember(UserRole $role = UserRole::Admin): User
{
    return User::factory()->withRole($role)->create();
}

function taggedProduct(string ...$tags): Product
{
    $product = Product::factory()->withVariant()->create();
    $product->tags()->attach(array_map(fn (string $name): int => Tag::query()->firstOrCreate(['name' => $name], ['slug' => str($name)->slug()->toString() ?: 'tag'])->id, $tags));
    app(RefreshProductSearchTextAction::class)->handle($product);

    return $product;
}

describe('tags', function () {
    it('lists tags with product counts and filters them while typing', function () {
        taggedProduct('summer', 'gift');
        taggedProduct('summer');

        actingAs(staffMember())
            ->getJson(route('api.v1.admin.tags.index', ['q' => 'SUM']))
            ->assertOk()
            ->assertJsonPath('data.*.name', ['summer'])
            ->assertJsonPath('data.0.products_count', 2);
    });

    it('renames a tag, updating its slug and the search text of its products', function () {
        $product = taggedProduct('summer');
        $tag = Tag::query()->where('name', 'summer')->sole();

        actingAs(staffMember())
            ->putJson(route('api.v1.admin.tags.update', ['tag' => $tag]), ['name' => 'تابستان'])
            ->assertOk()
            ->assertJsonPath('data.slug', 'tabstan');

        expect($product->refresh()->search_text)->toContain('تابستان')->not->toContain('summer');
    });

    it('merges a duplicate tag into another, keeping its products', function () {
        $first = taggedProduct('gift');
        $second = taggedProduct('gifts', 'gift');
        $duplicate = Tag::query()->where('name', 'gifts')->sole();
        $into = Tag::query()->where('name', 'gift')->sole();

        actingAs(staffMember())
            ->postJson(route('api.v1.admin.tags.merge', ['tag' => $duplicate]), ['into_tag_id' => $into->id])
            ->assertOk()
            ->assertJsonPath('data.products_count', 2);

        expect(Tag::query()->pluck('name')->all())->toBe(['gift'])
            ->and($first->tags()->count())->toBe(1)
            ->and($second->tags()->count())->toBe(1);

        actingAs(staffMember())
            ->postJson(route('api.v1.admin.tags.merge', ['tag' => $into]), ['into_tag_id' => $into->id])
            ->assertUnprocessable();
    });

    it('deletes a tag and needs products.manage to change tags', function () {
        $product = taggedProduct('old');
        $tag = Tag::query()->sole();

        actingAs(staffMember(UserRole::Author))->deleteJson(route('api.v1.admin.tags.destroy', ['tag' => $tag]))->assertForbidden();
        actingAs(staffMember())->deleteJson(route('api.v1.admin.tags.destroy', ['tag' => $tag]))->assertNoContent();

        expect($product->tags()->count())->toBe(0)
            ->and($product->refresh()->search_text)->not->toContain('old');
    });
});

describe('settings', function () {
    it('lets every staff member read the settings, with defaults', function () {
        actingAs(staffMember(UserRole::Author))
            ->getJson(route('api.v1.admin.settings.show'))
            ->assertOk()
            ->assertExactJson(['data' => ['price_display_currency' => 'IRT', 'low_stock_threshold' => 5, 'storefront_preview_url' => null, 'default_weight_grams' => null]]);
    });

    it('lets only the super admin change them', function () {
        actingAs(staffMember())
            ->putJson(route('api.v1.admin.settings.update'), ['price_display_currency' => 'IRR'])
            ->assertForbidden();

        actingAs(staffMember(UserRole::SuperAdmin))
            ->putJson(route('api.v1.admin.settings.update'), ['price_display_currency' => 'IRR', 'low_stock_threshold' => 2])
            ->assertOk()
            ->assertJsonPath('data.price_display_currency', 'IRR')
            ->assertJsonPath('data.low_stock_threshold', 2);

        expect(app(Settings::class)->lowStockThreshold())->toBe(2);

        actingAs(staffMember(UserRole::SuperAdmin))
            ->putJson(route('api.v1.admin.settings.update'), ['price_display_currency' => 'USD'])
            ->assertUnprocessable();
    });

    it('accepts a storefront preview address only with a {token} placeholder, and clears it with null', function (mixed $url, bool $accepted) {
        $response = actingAs(staffMember(UserRole::SuperAdmin))
            ->putJson(route('api.v1.admin.settings.update'), ['storefront_preview_url' => $url]);

        $accepted
            ? $response->assertOk()->assertJsonPath('data.storefront_preview_url', $url)
            : $response->assertUnprocessable()->assertJsonValidationErrors('storefront_preview_url');
    })->with([
        'with the placeholder' => ['https://shop.example/preview?token={token}', true],
        'in the path' => ['http://localhost:3000/preview/{token}', true],
        'cleared' => [null, true],
        'without the placeholder' => ['https://shop.example/preview', false],
        'not http' => ['javascript:alert(1)//{token}', false],
        'relative' => ['/preview?token={token}', false],
    ]);

    it('forgets a cleared preview address', function () {
        app(Settings::class)->set([Settings::STOREFRONT_PREVIEW_URL => 'https://shop.example/p/{token}']);
        app(Settings::class)->set([Settings::STOREFRONT_PREVIEW_URL => null]);

        expect(app(Settings::class)->storefrontPreviewUrl())->toBeNull();
    });

    it('uses the store low-stock threshold unless the product has its own', function () {
        app(Settings::class)->set([Settings::LOW_STOCK_THRESHOLD => 3]);
        $storeDefault = Product::factory()->withVariant(stock: 3)->create();
        $own = Product::factory()->withVariant(stock: 3)->create(['low_stock_threshold' => 1]);

        actingAs(staffMember())
            ->getJson(route('api.v1.admin.products.index', ['stock' => 'low']))
            ->assertJsonPath('data.*.id', [$storeDefault->id]);

        expect($own->lowStockThreshold())->toBe(1);
    });
});

describe('protections now that products exist', function () {
    it('refuses to delete a category or brand with products, deleted products included', function () {
        $category = Category::factory()->create();
        $brand = Brand::factory()->create();
        $product = Product::factory()->withVariant()->inCategory($category)->create(['brand_id' => $brand->id]);
        $product->delete();

        withHeader('Accept-Language', 'en')
            ->actingAs(staffMember())
            ->deleteJson(route('api.v1.admin.categories.destroy', ['category' => $category]))
            ->assertConflict()
            ->assertJsonPath('code', 'category_has_products');

        withHeader('Accept-Language', 'en')
            ->actingAs(staffMember())
            ->deleteJson(route('api.v1.admin.brands.destroy', ['brand' => $brand]))
            ->assertConflict()
            ->assertJsonPath('code', 'brand_has_products');
    });

    it('refuses to delete attributes, options and values that products use', function () {
        $material = Attribute::factory()->select(['Cotton' => 'cotton', 'Linen' => 'linen'])->create(['slug' => 'material']);
        $size = Option::factory()->withValues(['S' => 's', 'M' => 'm'])->create(['slug' => 'size']);
        $product = Product::factory()->create(['type' => 'variable']);
        $product->attributeValues()->create(['attribute_id' => $material->id, 'attribute_value_id' => $material->values[0]->id]);
        $product->options()->attach($size, ['position' => 0]);
        ProductVariant::factory()->for($product)->create()->optionValues()->attach($size->values[0]);
        $admin = staffMember();

        actingAs($admin)->deleteJson(route('api.v1.admin.attributes.destroy', ['attribute' => $material]))
            ->assertConflict()->assertJsonPath('code', 'catalog_item_in_use');
        actingAs($admin)->deleteJson(route('api.v1.admin.options.destroy', ['option' => $size]))
            ->assertConflict()->assertJsonPath('code', 'catalog_item_in_use');

        // Removing the used values (Cotton, S) is refused; the unused ones could go.
        withHeader('Accept-Language', 'en')->actingAs($admin)
            ->putJson(route('api.v1.admin.attributes.update', ['attribute' => $material]), [
                'name' => $material->name, 'slug' => 'material', 'type' => 'select', 'is_filterable' => false,
                'values' => [['id' => $material->values[1]->id, 'value' => 'Linen', 'slug' => 'linen']],
            ])
            ->assertConflict()
            ->assertJsonPath('code', 'catalog_value_in_use')
            ->assertJsonPath('message', 'Products use “Cotton”, so it can’t be removed. Remove it from those products first.');

        actingAs($admin)
            ->putJson(route('api.v1.admin.options.update', ['option' => $size]), [
                'name' => $size->name, 'slug' => 'size', 'display_type' => 'text',
                'values' => [['id' => $size->values[1]->id, 'value' => 'M', 'slug' => 'm']],
            ])
            ->assertConflict()
            ->assertJsonPath('code', 'catalog_value_in_use');

        expect($material->values()->count())->toBe(2)->and($size->values()->count())->toBe(2);
    });

    it('refuses to change the type of an attribute that products use', function () {
        $material = Attribute::factory()->select()->create(['slug' => 'material']);
        Product::factory()->create()->attributeValues()->create(['attribute_id' => $material->id, 'attribute_value_id' => $material->values[0]->id]);

        actingAs(staffMember())
            ->putJson(route('api.v1.admin.attributes.update', ['attribute' => $material]), [
                'name' => $material->name, 'slug' => 'material', 'type' => 'text', 'is_filterable' => false, 'values' => [],
            ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'attribute_type_locked')
            ->assertJsonValidationErrors(['type']);
    });

    it('refreshes product search text when a brand or category is renamed', function () {
        $category = Category::factory()->create(['name' => 'Shoes', 'slug' => 'shoes']);
        $brand = Brand::factory()->create(['name' => 'Nike', 'slug' => 'nike']);
        $product = Product::factory()->withVariant()->inCategory($category)->create(['brand_id' => $brand->id]);
        app(RefreshProductSearchTextAction::class)->handle($product);
        $admin = staffMember();

        actingAs($admin)->putJson(route('api.v1.admin.brands.update', ['brand' => $brand]), [
            'name' => 'Nike', 'persian_name' => 'نایکی', 'slug' => 'nike', 'is_active' => true,
        ])->assertOk();
        actingAs($admin)->putJson(route('api.v1.admin.categories.update', ['category' => $category]), [
            'name' => 'کفش', 'slug' => 'shoes', 'parent_id' => null, 'is_active' => true,
        ])->assertOk();

        expect($product->refresh()->search_text)->toContain('نایکی')->toContain('کفش')->not->toContain('shoes');
    });
});

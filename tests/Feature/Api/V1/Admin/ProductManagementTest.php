<?php

declare(strict_types=1);

use App\Actions\Products\AdjustStockAction;
use App\Actions\Products\MatchingProductsAction;
use App\Enums\StockMovementReason;
use App\Enums\UserRole;
use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\MediaAsset;
use App\Models\Option;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\withHeader;

function productManager(UserRole $role = UserRole::Admin): User
{
    return User::factory()->withRole($role)->create();
}

/**
 * A valid simple product; amounts are Rials.
 *
 * @return array<string, mixed>
 */
function productPayload(array $overrides = []): array
{
    return [
        'type' => 'simple',
        'name' => 'پیراهن مردانه نخی',
        'slug' => 'cotton-shirt',
        'short_description' => null,
        'description' => null,
        'status' => 'draft',
        'published_at' => null,
        'brand_id' => null,
        'category_ids' => [],
        'primary_category_id' => null,
        'tags' => [],
        'track_stock' => true,
        'allow_backorders' => false,
        'low_stock_threshold' => null,
        'option_ids' => [],
        'variants' => [['sku' => 'SH-001', 'price' => 12_000_000, 'is_enabled' => true, 'stock_quantity' => 7]],
        'specifications' => [],
        ...$overrides,
    ];
}

/**
 * @return array{0: Option, 1: Option}
 */
function sizeAndColor(): array
{
    return [
        Option::factory()->withValues(['S' => 's', 'M' => 'm'])->create(['slug' => 'size']),
        Option::factory()->withValues(['Red' => 'red', 'Blue' => 'blue'])->create(['slug' => 'color', 'display_type' => 'color']),
    ];
}

describe('access', function () {
    it('returns 401 to guests and 403 to roles without the product permissions', function () {
        getJson(route('api.v1.admin.products.index'))->assertUnauthorized();

        actingAs(productManager(UserRole::Author))->getJson(route('api.v1.admin.products.index'))->assertForbidden();
        actingAs(productManager(UserRole::Author))->postJson(route('api.v1.admin.products.store'), productPayload())->assertForbidden();
    });
});

describe('create', function () {
    it('creates a simple product with categories, brand, tags, sanitized description, stock and SEO score', function () {
        $clothing = Category::factory()->create(['name' => 'پوشاک']);
        $shirts = Category::factory()->childOf($clothing)->create(['name' => 'پیراهن']);
        $brand = Brand::factory()->create(['name' => 'Adidas', 'persian_name' => 'آدیداس']);
        Tag::query()->create(['name' => 'هدیه', 'slug' => 'hedie']);
        $admin = productManager();

        $response = actingAs($admin)
            ->postJson(route('api.v1.admin.products.store'), productPayload([
                'brand_id' => $brand->id,
                'category_ids' => [$clothing->id, $shirts->id],
                'primary_category_id' => $shirts->id,
                // "هديه" (Arabic yeh) matches the existing «هدیه»; "Summer" is new.
                'tags' => ['هديه', 'Summer', 'summer'],
                'description' => '<p onclick="steal()">Soft <strong>cotton</strong></p><script>alert(1)</script>',
                'focus_keyphrase' => 'پیراهن مردانه',
            ]))
            ->assertCreated()
            ->assertJsonPath('data.type', 'simple')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.published_at', null)
            ->assertJsonPath('data.brand.name', 'Adidas')
            ->assertJsonPath('data.description', '<p>Soft <strong>cotton</strong></p>')
            ->assertJsonPath('data.variants.0.sku', 'SH-001')
            ->assertJsonPath('data.variants.0.price', 12_000_000)
            ->assertJsonPath('data.variants.0.stock_quantity', 7);

        expect(collect($response->json('data.categories'))->firstWhere('is_primary', true)['id'])->toBe($shirts->id)
            ->and(collect($response->json('data.tags'))->pluck('name')->sort()->values()->all())->toBe(['Summer', 'هدیه'])
            ->and(Tag::query()->count())->toBe(2)
            ->and($response->json('data.seo_score'))->toBeInt();

        $product = Product::query()->sole();

        expect($product->search_text)->toContain('adidas')->toContain('آدیداس')->toContain('sh')->toContain('پوشاک')
            ->and(StockMovement::query()->sole()->only(['reason', 'quantity_change', 'quantity_before', 'quantity_after', 'user_id']))
            ->toBe(['reason' => StockMovementReason::Initial, 'quantity_change' => 7, 'quantity_before' => 0, 'quantity_after' => 7, 'user_id' => $admin->id])
            ->and(Activity::query()->where('event', 'product.created')->sole()->properties['name'])->toBe('پیراهن مردانه نخی');
    });

    it('publishes now when no date is given and keeps a future date as scheduled', function () {
        $category = Category::factory()->create();

        $now = actingAs(productManager())
            ->postJson(route('api.v1.admin.products.store'), productPayload(['status' => 'published', 'category_ids' => [$category->id]]))
            ->assertCreated()
            ->json('data.published_at');

        $future = now()->addWeek()->startOfMinute()->toIso8601String();
        actingAs(productManager())
            ->postJson(route('api.v1.admin.products.store'), productPayload([
                'slug' => 'later', 'status' => 'published', 'published_at' => $future, 'category_ids' => [$category->id],
                'variants' => [['sku' => 'LATER', 'price' => 1, 'is_enabled' => true]],
            ]))
            ->assertCreated()
            ->assertJsonPath('data.published_at', $future);

        expect($now)->not->toBeNull();
    });

    it('refuses to publish without a category or an enabled variant', function () {
        actingAs(productManager())
            ->postJson(route('api.v1.admin.products.store'), productPayload(['status' => 'published']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category_ids']);

        actingAs(productManager())
            ->postJson(route('api.v1.admin.products.store'), productPayload([
                'status' => 'published',
                'category_ids' => [Category::factory()->create()->id],
                'variants' => [['sku' => 'X', 'price' => 1, 'is_enabled' => false]],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['variants']);
    });

    it('validates variants and prices', function (array $overrides, string $field) {
        ProductVariant::factory()->create(['sku' => 'TAKEN-1']);

        withHeader('Accept-Language', 'en')
            ->actingAs(productManager())
            ->postJson(route('api.v1.admin.products.store'), productPayload($overrides))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field]);
    })->with([
        'two variants on a simple product' => [['variants' => [
            ['sku' => 'A', 'price' => 1, 'is_enabled' => true], ['sku' => 'B', 'price' => 1, 'is_enabled' => true],
        ]], 'variants'],
        'sku of another product (any case)' => [['variants' => [['sku' => 'taken-1', 'price' => 1, 'is_enabled' => true]]], 'variants.0.sku'],
        'sku with spaces' => [['variants' => [['sku' => 'A B', 'price' => 1, 'is_enabled' => true]]], 'variants.0.sku'],
        'negative price' => [['variants' => [['sku' => 'A', 'price' => -1, 'is_enabled' => true]]], 'variants.0.price'],
        'sale not lower' => [['variants' => [['sku' => 'A', 'price' => 100, 'sale_price' => 100, 'is_enabled' => true]]], 'variants.0.sale_price'],
        'sale ends before it starts' => [['variants' => [[
            'sku' => 'A', 'price' => 100, 'sale_price' => 50, 'sale_starts_at' => '2026-10-10', 'sale_ends_at' => '2026-10-09', 'is_enabled' => true,
        ]]], 'variants.0.sale_ends_at'],
        'image not in the gallery' => [['variants' => [['sku' => 'A', 'price' => 1, 'image_id' => 99, 'is_enabled' => true]]], 'variants.0.image_id'],
        'primary category not among the categories' => [['category_ids' => [], 'primary_category_id' => 5], 'primary_category_id'],
    ]);
});

describe('variable products', function () {
    it('creates a variant per option value combination with its own price and sale', function () {
        [$size, $color] = sizeAndColor();
        [$s, $m] = $size->values->all();
        [$red, $blue] = $color->values->all();

        $response = actingAs(productManager())
            ->postJson(route('api.v1.admin.products.store'), productPayload([
                'type' => 'variable',
                'option_ids' => [$size->id, $color->id],
                'variants' => [
                    ['sku' => 'T-S-RED', 'price' => 100, 'is_enabled' => true, 'option_value_ids' => [$s->id, $red->id], 'stock_quantity' => 2],
                    ['sku' => 'T-S-BLUE', 'price' => 100, 'sale_price' => 80, 'is_enabled' => true, 'option_value_ids' => [$blue->id, $s->id]],
                    ['sku' => 'T-M-RED', 'price' => 120, 'is_enabled' => false, 'option_value_ids' => [$m->id, $red->id]],
                ],
            ]))
            ->assertCreated()
            ->assertJsonPath('data.options.*.id', [$size->id, $color->id])
            ->assertJsonCount(2, 'data.options.0.values')
            ->assertJsonPath('data.variants.*.sku', ['T-S-RED', 'T-S-BLUE', 'T-M-RED'])
            ->assertJsonPath('data.variants.1.current_price', 80)
            ->assertJsonPath('data.variants.1.discount_percent', 20)
            ->assertJsonPath('data.variants.1.option_value_ids', collect([$s->id, $blue->id])->sort()->values()->all());

        expect($response->json('data.variants.2.is_enabled'))->toBeFalse();
    });

    it('needs exactly one value of each chosen option and no duplicate combinations', function (string $case) {
        [$size, $color] = sizeAndColor();
        [$s, $m] = $size->values->all();
        [$red] = $color->values->all();
        $values = match ($case) {
            'missing an option' => [[$s->id], [$m->id, $red->id]],
            'two values of one option' => [[$s->id, $m->id], [$m->id, $red->id]],
            'duplicate combination' => [[$s->id, $red->id], [$red->id, $s->id]],
        };

        actingAs(productManager())
            ->postJson(route('api.v1.admin.products.store'), productPayload([
                'type' => 'variable',
                'option_ids' => [$size->id, $color->id],
                'variants' => [
                    ['sku' => 'A', 'price' => 1, 'is_enabled' => true, 'option_value_ids' => $values[0]],
                    ['sku' => 'B', 'price' => 1, 'is_enabled' => true, 'option_value_ids' => $values[1]],
                ],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$case === 'duplicate combination' ? 'variants.1.option_value_ids' : 'variants.0.option_value_ids']);
    })->with(['missing an option', 'two values of one option', 'duplicate combination']);
});

describe('update', function () {
    it('adjusts stock from the quantity the admin saw and records the movement', function () {
        $admin = productManager();
        $product = actingAs($admin)->postJson(route('api.v1.admin.products.store'), productPayload())->json('data');
        $variant = $product['variants'][0];

        actingAs($admin)
            ->putJson(route('api.v1.admin.products.update', ['product' => $product['id']]), productPayload(['variants' => [[
                'id' => $variant['id'], 'sku' => 'SH-001', 'price' => 12_000_000, 'is_enabled' => true,
                'stock_quantity' => 3, 'stock_quantity_was' => 7,
            ]]]))
            ->assertOk()
            ->assertJsonPath('data.variants.0.stock_quantity', 3);

        expect(StockMovement::query()->latest('id')->first()?->only(['quantity_change', 'quantity_before', 'quantity_after']))
            ->toBe(['quantity_change' => -4, 'quantity_before' => 7, 'quantity_after' => 3]);
    });

    it('refuses to overwrite stock that changed meanwhile', function () {
        $admin = productManager();
        $product = actingAs($admin)->postJson(route('api.v1.admin.products.store'), productPayload())->json('data');
        $variant = ProductVariant::query()->findOrFail($product['variants'][0]['id']);

        // Stock moved after the admin loaded the form (e.g. an order).
        app(AdjustStockAction::class)->handle($variant, -2, StockMovementReason::Order);

        withHeader('Accept-Language', 'en')
            ->actingAs($admin)
            ->putJson(route('api.v1.admin.products.update', ['product' => $product['id']]), productPayload(['variants' => [[
                'id' => $variant->id, 'sku' => 'SH-001', 'price' => 1, 'is_enabled' => true,
                'stock_quantity' => 10, 'stock_quantity_was' => 7,
            ]]]))
            ->assertConflict()
            ->assertJsonPath('code', 'stock_changed')
            ->assertJsonValidationErrors(['variants.0.stock_quantity']);

        expect($variant->refresh()->stock_quantity)->toBe(5)
            ->and($variant->price)->toBe(12_000_000);
    });

    it('soft-deletes removed variants, keeping their SKU taken', function () {
        [$size] = sizeAndColor();
        [$s, $m] = $size->values->all();
        $admin = productManager();
        $product = actingAs($admin)->postJson(route('api.v1.admin.products.store'), productPayload([
            'type' => 'variable',
            'option_ids' => [$size->id],
            'variants' => [
                ['sku' => 'V-S', 'price' => 1, 'is_enabled' => true, 'option_value_ids' => [$s->id]],
                ['sku' => 'V-M', 'price' => 1, 'is_enabled' => true, 'option_value_ids' => [$m->id]],
            ],
        ]))->json('data');

        actingAs($admin)
            ->putJson(route('api.v1.admin.products.update', ['product' => $product['id']]), productPayload([
                'type' => 'variable',
                'option_ids' => [$size->id],
                'variants' => [['id' => $product['variants'][0]['id'], 'sku' => 'V-S', 'price' => 1, 'is_enabled' => true, 'option_value_ids' => [$s->id]]],
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data.variants');

        expect(ProductVariant::withTrashed()->where('sku', 'V-M')->sole()->trashed())->toBeTrue();

        actingAs($admin)
            ->postJson(route('api.v1.admin.products.store'), productPayload(['slug' => 'other', 'variants' => [['sku' => 'V-M', 'price' => 1, 'is_enabled' => true]]]))
            ->assertJsonValidationErrors(['variants.0.sku']);
    });

    it('logs the changed parts and does not log an unchanged save', function () {
        $admin = productManager();
        $product = actingAs($admin)->postJson(route('api.v1.admin.products.store'), productPayload(['tags' => ['a']]))->json('data');
        $variant = ['id' => $product['variants'][0]['id'], 'sku' => 'SH-001', 'price' => 12_000_000, 'is_enabled' => true];

        actingAs($admin)->putJson(route('api.v1.admin.products.update', ['product' => $product['id']]), productPayload(['tags' => ['a'], 'variants' => [$variant]]))->assertOk();
        expect(Activity::query()->where('event', 'product.updated')->count())->toBe(0);

        actingAs($admin)->putJson(route('api.v1.admin.products.update', ['product' => $product['id']]), productPayload([
            'name' => 'New name', 'tags' => ['b'], 'variants' => [[...$variant, 'price' => 1]],
        ]))->assertOk();

        expect(Activity::query()->where('event', 'product.updated')->sole()->properties['fields'])->toBe(['name', 'tags', 'variants']);
    });
});

describe('specifications', function () {
    it('accepts the specifications of the product’s categories and their ancestors', function () {
        $material = Attribute::factory()->select(['Cotton' => 'cotton', 'Linen' => 'linen'])->create();
        $screen = Attribute::factory()->create(['type' => 'number', 'unit' => 'inch']);
        $waterproof = Attribute::factory()->create(['type' => 'boolean']);
        $clothing = Category::factory()->create();
        $shirts = Category::factory()->childOf($clothing)->create();
        $clothing->attributeDefinitions()->attach($material);
        $shirts->attributeDefinitions()->attach([$screen->id, $waterproof->id]);

        $response = actingAs(productManager())
            ->postJson(route('api.v1.admin.products.store'), productPayload([
                'category_ids' => [$shirts->id],
                'specifications' => [
                    ['attribute_id' => $material->id, 'value_ids' => $material->values->pluck('id')->all()],
                    ['attribute_id' => $screen->id, 'number' => 15.6],
                    ['attribute_id' => $waterproof->id, 'boolean' => false],
                ],
            ]))
            ->assertCreated();

        $specs = collect($response->json('data.specifications'))->keyBy('attribute_id');

        expect($specs[$material->id]['value_ids'])->toHaveCount(2)
            ->and((float) $specs[$screen->id]['number'])->toBe(15.6)
            ->and($specs[$waterproof->id]['boolean'])->toBeFalse();
    });

    it('refuses specifications outside the categories and invalid values', function (string $case) {
        $material = Attribute::factory()->select()->create();
        $other = Attribute::factory()->create();
        $category = Category::factory()->create();
        $category->attributeDefinitions()->attach($material);

        $specification = match ($case) {
            'not applicable' => ['attribute_id' => $other->id, 'text' => 'x'],
            'value of another attribute' => ['attribute_id' => $material->id, 'value_ids' => [Attribute::factory()->select()->create()->values[0]->id]],
            'no value' => ['attribute_id' => $material->id, 'value_ids' => []],
        };

        actingAs(productManager())
            ->postJson(route('api.v1.admin.products.store'), productPayload(['category_ids' => [$category->id], 'specifications' => [$specification]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$case === 'not applicable' ? 'specifications.0.attribute_id' : 'specifications.0']);
    })->with(['not applicable', 'value of another attribute', 'no value']);

    it('lists the applicable specifications for chosen categories', function () {
        [$a, $b] = Attribute::factory()->count(2)->create()->all();
        $parent = Category::factory()->create();
        $child = Category::factory()->childOf($parent)->create();
        $parent->attributeDefinitions()->attach($a);
        $child->attributeDefinitions()->attach([$b->id, $a->id]);

        actingAs(productManager())
            ->getJson(route('api.v1.admin.attributes.applicable', ['category_ids' => [$child->id]]))
            ->assertOk()
            ->assertJsonPath('data.*.id', [$a->id, $b->id]);
    });
});

describe('images', function () {
    beforeEach(fn () => Storage::fake('public'));

    it('uses library images as the gallery, in order; variant pictures come from the gallery', function () {
        $admin = productManager();
        $front = libraryImage('front.jpg', ['alt' => 'Front']);
        $back = libraryImage('back.png');
        $product = actingAs($admin)
            ->postJson(route('api.v1.admin.products.store'), productPayload(['image_ids' => [$front->id, $back->id]]))
            ->assertCreated()
            ->assertJsonPath('data.images.*.id', [$front->id, $back->id])
            ->assertJsonPath('data.images.0.alt', 'Front')
            ->json('data');

        actingAs($admin)->putJson(route('api.v1.admin.products.update', ['product' => $product['id']]), productPayload([
            'image_ids' => [$back->id, $front->id],
            'variants' => [['id' => $product['variants'][0]['id'], 'sku' => 'SH-001', 'price' => 1, 'is_enabled' => true, 'image_id' => $back->id]],
        ]))
            ->assertOk()
            ->assertJsonPath('data.images.*.id', [$back->id, $front->id])
            ->assertJsonPath('data.variants.0.image_id', $back->id);

        // A variant can't keep a picture that leaves the gallery.
        actingAs($admin)->putJson(route('api.v1.admin.products.update', ['product' => $product['id']]), productPayload([
            'image_ids' => [$front->id],
            'variants' => [['id' => $product['variants'][0]['id'], 'sku' => 'SH-001', 'price' => 1, 'is_enabled' => true, 'image_id' => $back->id]],
        ]))->assertUnprocessable()->assertJsonValidationErrors(['variants.0.image_id']);

        expect(Activity::query()->where('event', 'product.updated')->sole()->properties['fields'])->toContain('images')
            ->and($front->isInUse())->toBeTrue();
    });

    it('only accepts up to 30 library images that are not in the trash', function (string $case) {
        $ids = match ($case) {
            'pdf' => [MediaAsset::factory()->document()->create()->id],
            'trashed image' => [tap(MediaAsset::factory()->create())->delete()->id],
            'too many' => MediaAsset::factory()->count(31)->create()->modelKeys(),
        };

        actingAs(productManager())
            ->postJson(route('api.v1.admin.products.store'), productPayload(['image_ids' => $ids]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$case === 'too many' ? 'image_ids' : 'image_ids.0']);
    })->with(['pdf', 'trashed image', 'too many']);

    it('keeps the gallery of a deleted product in use, as it can be restored', function () {
        $image = libraryImage();
        $admin = productManager();
        $product = actingAs($admin)->postJson(route('api.v1.admin.products.store'), productPayload(['image_ids' => [$image->id]]))->json('data');

        actingAs($admin)->deleteJson(route('api.v1.admin.products.destroy', ['product' => $product['id']]))->assertNoContent();

        expect($image->isInUse())->toBeTrue();
    });
});

describe('list', function () {
    it('searches name, SKU and brand with any keyboard, and filters', function () {
        $clothing = Category::factory()->create();
        $shirts = Category::factory()->childOf($clothing)->create();
        $brand = Brand::factory()->create(['name' => 'Adidas', 'persian_name' => 'آدیداس']);
        $admin = productManager();

        $shirt = actingAs($admin)->postJson(route('api.v1.admin.products.store'), productPayload([
            'name' => 'کفش ورزشی', 'brand_id' => $brand->id, 'category_ids' => [$shirts->id], 'status' => 'published',
            'variants' => [['sku' => 'SHOE-42', 'price' => 5, 'is_enabled' => true, 'stock_quantity' => 2]],
        ]))->json('data');
        $other = actingAs($admin)->postJson(route('api.v1.admin.products.store'), productPayload([
            'slug' => 'other', 'name' => 'Hat', 'variants' => [['sku' => 'HAT', 'price' => 9, 'is_enabled' => true, 'stock_quantity' => 50]],
        ]))->json('data');

        $ids = fn (array $query): array => actingAs($admin)->getJson(route('api.v1.admin.products.index', $query))->assertOk()->json('data.*.id');

        expect($ids(['q' => 'كفش']))->toBe([$shirt['id']])
            ->and($ids(['q' => 'shoe-42']))->toBe([$shirt['id']])
            ->and($ids(['q' => 'آديداس']))->toBe([$shirt['id']])
            ->and($ids(['status' => 'draft']))->toBe([$other['id']])
            ->and($ids(['category_id' => $clothing->id]))->toBe([$shirt['id']])
            ->and($ids(['brand_id' => $brand->id]))->toBe([$shirt['id']])
            ->and($ids(['stock' => 'low']))->toBe([$shirt['id']]);

        actingAs($admin)->getJson(route('api.v1.admin.products.index', ['q' => 'hat']))
            ->assertJsonPath('data.0.price', ['min' => 9, 'max' => 9])
            ->assertJsonPath('data.0.stock', ['total' => 50, 'low' => false, 'out' => false]);
    });

    it('finds out-of-stock products, respecting backorders', function () {
        Product::factory()->withVariant(stock: 0)->create(['name' => 'Out']);
        Product::factory()->withVariant(stock: 0)->create(['name' => 'Backorder', 'allow_backorders' => true]);
        Product::factory()->withVariant(stock: 3)->create(['name' => 'In']);

        actingAs(productManager())
            ->getJson(route('api.v1.admin.products.index', ['stock' => 'out']))
            ->assertJsonPath('data.*.name', ['Out']);
    });

    it('finds products with something to sell: stock left, backorders or untracked stock', function () {
        Product::factory()->withVariant(stock: 0)->create(['name' => 'Out']);
        Product::factory()->withVariant(stock: 0)->create(['name' => 'Backorder', 'allow_backorders' => true]);
        Product::factory()->withVariant(stock: 0)->create(['name' => 'Untracked', 'track_stock' => false]);
        Product::factory()->withVariant(stock: 3)->create(['name' => 'In']);

        actingAs(productManager())
            ->getJson(route('api.v1.admin.products.index', ['stock' => 'in', 'sort' => 'name']))
            ->assertJsonPath('data.*.name', ['Backorder', 'In', 'Untracked']);
    });

    it('returns every product matching the filters, up to the limit', function () {
        $brand = Brand::factory()->create();
        $first = Product::factory()->withVariant()->create(['brand_id' => $brand->id]);
        $second = Product::factory()->withVariant()->create(['brand_id' => $brand->id]);
        Product::factory()->withVariant()->create();

        actingAs(productManager())
            ->getJson(route('api.v1.admin.products.matching', ['brand_id' => $brand->id]))
            ->assertOk()
            ->assertExactJson(['data' => ['products' => [['id' => $second->id, 'name' => $second->name], ['id' => $first->id, 'name' => $first->name]], 'total' => 2, 'limit' => MatchingProductsAction::LIMIT]]);
    });

    it('deletes, lists deleted products and restores them', function () {
        $product = Product::factory()->withVariant()->create();
        $admin = productManager();

        actingAs($admin)->deleteJson(route('api.v1.admin.products.destroy', ['product' => $product]))->assertNoContent();

        actingAs($admin)->getJson(route('api.v1.admin.products.index'))->assertJsonCount(0, 'data');
        actingAs($admin)->getJson(route('api.v1.admin.products.index', ['trashed' => 1]))->assertJsonPath('data.0.id', $product->id);
        actingAs($admin)->getJson(route('api.v1.admin.products.show', ['product' => $product]))->assertOk();

        actingAs($admin)->postJson(route('api.v1.admin.products.restore', ['product' => $product]))->assertOk()->assertJsonPath('data.deleted_at', null);

        expect(Activity::query()->whereIn('event', ['product.deleted', 'product.restored'])->count())->toBe(2);
    });

    it('changes the status of several products, skipping those that can’t be published', function () {
        $ready = Product::factory()->withVariant()->inCategory(Category::factory()->create())->create();
        $noCategory = Product::factory()->withVariant()->create();

        actingAs(productManager())
            ->postJson(route('api.v1.admin.products.status'), ['ids' => [$ready->id, $noCategory->id], 'status' => 'published'])
            ->assertOk()
            ->assertJsonPath('data.updated', [$ready->id])
            ->assertJsonPath('data.skipped', [$noCategory->id]);

        expect($ready->refresh()->published_at)->not->toBeNull();
    });
});

describe('stock history', function () {
    it('lists the movements of the product’s variants, newest first', function () {
        $admin = productManager();
        $product = actingAs($admin)->postJson(route('api.v1.admin.products.store'), productPayload())->json('data');
        actingAs($admin)->putJson(route('api.v1.admin.products.update', ['product' => $product['id']]), productPayload(['variants' => [[
            'id' => $product['variants'][0]['id'], 'sku' => 'SH-001', 'price' => 1, 'is_enabled' => true, 'stock_quantity' => 9, 'stock_quantity_was' => 7,
        ]]]));

        actingAs($admin)
            ->getJson(route('api.v1.admin.products.stock-movements', ['product' => $product['id']]))
            ->assertOk()
            ->assertJsonPath('data.*.reason', ['adjustment', 'initial'])
            ->assertJsonPath('data.0.quantity_change', 2)
            ->assertJsonPath('data.0.user.id', $admin->id)
            ->assertJsonPath('data.0.variant.sku', 'SH-001');
    });
});

describe('descriptions', function () {
    beforeEach(fn () => Storage::fake('public'));

    it('keeps only library images and videos in the description, with addresses from the library', function () {
        $photo = libraryImage('front.jpg', ['alt' => 'Front']);
        $cover = libraryImage('cover.jpg');
        $video = MediaAsset::factory()->video()->create(['cover_id' => $cover->id]);
        $video->addMedia(UploadedFile::fake()->createWithContent('clip.mp4', 'video'))->toMediaCollection(MediaAsset::FILE_COLLECTION);
        $admin = productManager();

        $description = actingAs($admin)->postJson(route('api.v1.admin.products.store'), productPayload(['description' => implode('', [
            "<p>Look:</p><img data-media-id=\"{$photo->id}\" src=\"https://evil.test/x.png\">",
            "<video data-media-id=\"{$video->id}\" src=\"https://evil.test/x.mp4\"></video>",
            '<img src="https://evil.test/tracker.png">',
            '<img data-media-id="999999" src="/storage/1/a.jpg">',
            "<video data-media-id=\"{$photo->id}\"></video>",
        ])]))->assertCreated()->json('data.description');

        expect($description)->not->toContain('evil.test')
            ->and(substr_count($description, '<img'))->toBe(1)
            ->and(substr_count($description, '<video'))->toBe(1)
            ->and($description)->toContain('alt="Front"')
            ->and($description)->toContain('src="'.$photo->urls()['large'].'"')
            ->and($description)->toContain('poster="'.$cover->urls()['large'].'"')
            ->and($photo->isInUse())->toBeTrue()
            ->and($video->isInUse())->toBeTrue();

        actingAs($admin)->getJson(route('api.v1.admin.media.show', $photo))->assertJsonPath('data.usages.0.role', 'content');
    });

    it('frees description files once they are removed from it', function () {
        $photo = libraryImage();
        $admin = productManager();
        $product = actingAs($admin)->postJson(route('api.v1.admin.products.store'), productPayload([
            'description' => "<p>x</p><img data-media-id=\"{$photo->id}\">",
        ]))->json('data');

        actingAs($admin)->putJson(route('api.v1.admin.products.update', ['product' => $product['id']]), productPayload([
            'description' => '<p>x</p>',
            'variants' => [['id' => $product['variants'][0]['id'], 'sku' => 'SH-001', 'price' => 1, 'is_enabled' => true]],
        ]))->assertOk();

        expect($photo->isInUse())->toBeFalse();
    });

    it('limits the short description to 1000 characters of text and gives storefronts plain text too', function () {
        $admin = productManager();
        $category = Category::factory()->create();

        actingAs($admin)->postJson(route('api.v1.admin.products.store'), productPayload(['short_description' => '<p><strong>'.str_repeat('a', 1001).'</strong></p>']))
            ->assertJsonValidationErrors(['short_description']);

        $product = actingAs($admin)->postJson(route('api.v1.admin.products.store'), productPayload([
            'status' => 'published',
            'category_ids' => [$category->id],
            'short_description' => '<h2>Soft</h2><p><strong>cotton</strong> &amp; linen</p><img src="x">',
        ]))->assertCreated()->assertJsonPath('data.short_description', '<p>Soft</p><p><strong>cotton</strong> &amp; linen</p>')->json('data');

        getJson(route('api.v1.products.show', ['slug' => $product['slug']]))
            ->assertJsonPath('data.short_description_text', "Soft\ncotton & linen");
    });
});

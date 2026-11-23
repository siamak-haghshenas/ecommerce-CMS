<?php

declare(strict_types=1);

use App\Actions\Products\RefreshProductSearchTextAction;
use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Option;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tag;
use App\Support\Settings;

use function Pest\Laravel\getJson;

/**
 * A published product in a visible category, with variants (price in Rials, stock, option values).
 *
 * @param  list<array<string, mixed>>  $variants
 */
function catalogProduct(array $attributes = [], array $variants = [['price' => 1000]], ?Category $category = null): Product
{
    $product = Product::factory()->published()->create($attributes);
    $product->categories()->attach($category ?? Category::factory()->create(), ['is_primary' => true]);

    foreach ($variants as $position => $variant) {
        $optionValueIds = $variant['option_value_ids'] ?? [];
        $stock = $variant['stock'] ?? 10;
        unset($variant['option_value_ids'], $variant['stock']);

        $model = ProductVariant::factory()->for($product)->create(['position' => $position, ...$variant]);
        $model->forceFill(['stock_quantity' => $stock])->save();
        $model->optionValues()->attach($optionValueIds);
    }

    app(RefreshProductSearchTextAction::class)->handle($product);

    return $product;
}

/**
 * @return list<int>
 */
function listedIds(array $query = []): array
{
    return getJson(route('api.v1.products.index', $query))->assertOk()->json('data.*.id');
}

describe('visibility', function () {
    it('lists only published, current products with an enabled variant in a visible category', function () {
        $visible = catalogProduct();
        catalogProduct(['status' => 'draft']);
        catalogProduct(['published_at' => now()->addDay()]);
        catalogProduct()->delete();
        catalogProduct(variants: [['price' => 1, 'is_enabled' => false]]);
        $hidden = Category::factory()->hidden()->create();
        catalogProduct(category: Category::factory()->childOf($hidden)->create());

        expect(listedIds())->toBe([$visible->id]);
    });

    it('returns 404 for products that are not visible', function (string $case) {
        $product = match ($case) {
            'draft' => catalogProduct(['status' => 'draft']),
            'scheduled' => catalogProduct(['published_at' => now()->addDay()]),
            'hidden category' => catalogProduct(category: Category::factory()->hidden()->create()),
        };

        getJson(route('api.v1.products.show', ['slug' => $product->slug]))->assertNotFound();
    })->with(['draft', 'scheduled', 'hidden category']);
});

describe('list', function () {
    it('shows sale-aware prices in Rials, availability but no stock numbers, and the currency', function () {
        $brand = Brand::factory()->create(['name' => 'Nike', 'persian_name' => 'نایکی']);
        catalogProduct(['brand_id' => $brand->id, 'name' => 'Runner'], [
            ['price' => 2000, 'sale_price' => 1500, 'stock' => 0],
            ['price' => 3000, 'stock' => 0],
        ]);

        $card = getJson(route('api.v1.products.index'))
            ->assertOk()
            ->assertJsonPath('meta.currency', ['display' => 'IRT', 'rials_per_unit' => 10])
            ->json('data.0');

        expect($card['price'])->toBe(['min' => 1500, 'max' => 3000])
            ->and($card['regular_price'])->toBe(['min' => 2000, 'max' => 3000])
            ->and($card['discount_percent'])->toBe(25)
            ->and($card['in_stock'])->toBeFalse()
            ->and($card['brand']['persian_name'])->toBe('نایکی')
            ->and(json_encode($card))->not->toContain('stock_quantity');
    });

    it('gives simple products their variant and deals the soonest discount end', function () {
        $endsAt = now()->addHours(5)->startOfSecond();
        $simple = catalogProduct(['published_at' => now()->subDay()], [['price' => 2000, 'sale_price' => 1500, 'sale_ends_at' => $endsAt]]);
        catalogProduct(['published_at' => now()->subDays(2)], [
            ['price' => 2000, 'sale_price' => 1500],
            ['price' => 3000],
        ]);

        $cards = getJson(route('api.v1.products.index'))->assertOk()->json('data');

        expect($cards[0]['variant_id'])->toBe($simple->variants()->value('id'))
            ->and($cards[0]['discount_ends_at'])->toBe($endsAt->toIso8601String())
            ->and($cards[1]['variant_id'])->toBeNull()
            ->and($cards[1]['discount_ends_at'])->toBeNull();
    });

    it('treats untracked stock and backorders as available and hides inactive brands', function () {
        $hiddenBrand = Brand::factory()->hidden()->create();
        catalogProduct(['track_stock' => false, 'brand_id' => $hiddenBrand->id], [['price' => 1, 'stock' => 0]]);

        getJson(route('api.v1.products.index'))
            ->assertJsonPath('data.0.in_stock', true)
            ->assertJsonPath('data.0.brand', null);
    });

    it('ignores a sale that has not started or has ended', function () {
        catalogProduct(variants: [['price' => 1000, 'sale_price' => 500, 'sale_starts_at' => now()->addDay()]]);
        catalogProduct(variants: [['price' => 1000, 'sale_price' => 500, 'sale_ends_at' => now()->subMinute()]]);

        expect(getJson(route('api.v1.products.index'))->json('data.*.price.min'))->toBe([1000, 1000]);
    });

    it('sorts by current price, name and newest', function () {
        $cheapOnSale = catalogProduct(['name' => 'B', 'published_at' => now()->subDays(3)], [['price' => 9000, 'sale_price' => 100]]);
        $middle = catalogProduct(['name' => 'C', 'published_at' => now()->subDays(1)], [['price' => 500]]);
        $expensive = catalogProduct(['name' => 'A', 'published_at' => now()->subDays(2)], [['price' => 800]]);

        expect(listedIds(['sort' => 'price_asc']))->toBe([$cheapOnSale->id, $middle->id, $expensive->id])
            ->and(listedIds(['sort' => 'price_desc']))->toBe([$expensive->id, $middle->id, $cheapOnSale->id])
            ->and(listedIds(['sort' => 'name']))->toBe([$expensive->id, $cheapOnSale->id, $middle->id])
            ->and(listedIds())->toBe([$middle->id, $expensive->id, $cheapOnSale->id]);
    });

    it('paginates with at most 48 per page', function () {
        catalogProduct();

        getJson(route('api.v1.products.index', ['per_page' => 49]))->assertUnprocessable();
        getJson(route('api.v1.products.index', ['per_page' => 1]))->assertJsonPath('meta.per_page', 1)->assertJsonPath('meta.total', 1);
    });
});

describe('filters', function () {
    it('filters by category (with sub-categories), brand, tag and search with any keyboard', function () {
        $shoes = Category::factory()->create(['slug' => 'shoes']);
        $running = Category::factory()->childOf($shoes)->create(['slug' => 'running']);
        $brand = Brand::factory()->create(['slug' => 'nike']);
        $tag = Tag::query()->create(['name' => 'هدیه', 'slug' => 'gift']);

        $runner = catalogProduct(['name' => 'کفش دویدن', 'brand_id' => $brand->id], category: $running);
        $runner->tags()->attach($tag);
        app(RefreshProductSearchTextAction::class)->handle($runner);
        $other = catalogProduct(['name' => 'Hat']);

        expect(listedIds(['category' => 'shoes']))->toBe([$runner->id])
            ->and(listedIds(['brand' => 'nike']))->toBe([$runner->id])
            ->and(listedIds(['tag' => 'gift']))->toBe([$runner->id])
            ->and(listedIds(['q' => 'كفش']))->toBe([$runner->id])
            ->and(listedIds(['category' => 'unknown']))->toBe([])
            ->and(listedIds(['brand' => 'unknown']))->toBe([])
            ->and(listedIds())->toContain($other->id);
    });

    it('filters by select, number and boolean specifications', function () {
        $material = Attribute::factory()->select(['Cotton' => 'cotton', 'Linen' => 'linen', 'Wool' => 'wool'])->create(['slug' => 'material', 'is_filterable' => true]);
        $screen = Attribute::factory()->create(['slug' => 'screen', 'type' => 'number', 'is_filterable' => true]);
        $waterproof = Attribute::factory()->create(['slug' => 'waterproof', 'type' => 'boolean', 'is_filterable' => true]);
        $hiddenSpec = Attribute::factory()->create(['slug' => 'internal', 'type' => 'boolean', 'is_filterable' => false]);

        $cotton = catalogProduct();
        $cotton->attributeValues()->createMany([
            ['attribute_id' => $material->id, 'attribute_value_id' => $material->values[0]->id],
            ['attribute_id' => $screen->id, 'value_number' => 15.6],
            ['attribute_id' => $waterproof->id, 'value_boolean' => true],
            ['attribute_id' => $hiddenSpec->id, 'value_boolean' => true],
        ]);
        $linen = catalogProduct();
        $linen->attributeValues()->createMany([
            ['attribute_id' => $material->id, 'attribute_value_id' => $material->values[1]->id],
            ['attribute_id' => $screen->id, 'value_number' => 13],
            ['attribute_id' => $waterproof->id, 'value_boolean' => false],
        ]);

        expect(listedIds(['filter' => ['material' => 'cotton,wool']]))->toBe([$cotton->id])
            ->and(listedIds(['filter' => ['material' => 'cotton,linen']]))->toHaveCount(2)
            ->and(listedIds(['filter' => ['screen' => '14..']]))->toBe([$cotton->id])
            ->and(listedIds(['filter' => ['screen' => '..14']]))->toBe([$linen->id])
            ->and(listedIds(['filter' => ['waterproof' => '1']]))->toBe([$cotton->id])
            ->and(listedIds(['filter' => ['waterproof' => '0']]))->toBe([$linen->id])
            ->and(listedIds(['filter' => ['material' => 'cotton', 'waterproof' => '0']]))->toBe([])
            // Not filterable or unknown: matches nothing rather than being ignored.
            ->and(listedIds(['filter' => ['internal' => '1']]))->toBe([])
            ->and(listedIds(['filter' => ['nope' => 'x']]))->toBe([]);
    });

    it('needs one enabled variant matching every chosen option', function () {
        $size = Option::factory()->withValues(['S' => 's', 'XL' => 'xl'])->create(['slug' => 'size']);
        $color = Option::factory()->withValues(['Red' => 'red', 'Blue' => 'blue'])->create(['slug' => 'color']);
        [$s, $xl] = $size->values->all();
        [$red, $blue] = $color->values->all();

        // Red only in S, XL only in blue: no red XL.
        $tee = catalogProduct(variants: [
            ['price' => 1, 'option_value_ids' => [$s->id, $red->id]],
            ['price' => 1, 'option_value_ids' => [$xl->id, $blue->id]],
            ['price' => 1, 'option_value_ids' => [$xl->id, $red->id], 'is_enabled' => false],
        ]);

        expect(listedIds(['option' => ['color' => 'red']]))->toBe([$tee->id])
            ->and(listedIds(['option' => ['color' => 'red', 'size' => 'xl']]))->toBe([])
            ->and(listedIds(['option' => ['color' => 'red,blue', 'size' => 'xl']]))->toBe([$tee->id]);
    });

    it('filters by current price, stock and sale', function () {
        $sale = catalogProduct(variants: [['price' => 5000, 'sale_price' => 900, 'stock' => 0]]);
        $plain = catalogProduct(variants: [['price' => 2000, 'stock' => 3]]);

        expect(listedIds(['max_price' => 1000]))->toBe([$sale->id])
            ->and(listedIds(['min_price' => 1000, 'max_price' => 3000]))->toBe([$plain->id])
            ->and(listedIds(['in_stock' => 1]))->toBe([$plain->id])
            ->and(listedIds(['on_sale' => 1]))->toBe([$sale->id]);
    });

    it('lists the filters to offer with product counts', function () {
        $category = Category::factory()->create(['slug' => 'shirts']);
        $material = Attribute::factory()->select(['Cotton' => 'cotton', 'Linen' => 'linen'])->create(['slug' => 'material', 'is_filterable' => true]);
        $elsewhere = Attribute::factory()->select(['X' => 'x'])->create(['is_filterable' => true]);
        $category->attributeDefinitions()->attach($material);
        $color = Option::factory()->withValues(['Red' => 'red', 'Blue' => 'blue'])->create(['slug' => 'color', 'display_type' => 'color']);
        $brand = Brand::factory()->create(['slug' => 'nike']);

        foreach ([[1000, $color->values[0]->id], [3000, $color->values[0]->id]] as [$price, $valueId]) {
            catalogProduct(['brand_id' => $brand->id], [['price' => $price, 'option_value_ids' => [$valueId]]], $category)
                ->attributeValues()->create(['attribute_id' => $material->id, 'attribute_value_id' => $material->values[0]->id]);
        }
        catalogProduct()->attributeValues()->create(['attribute_id' => $elsewhere->id, 'attribute_value_id' => $elsewhere->values[0]->id]);

        $filters = getJson(route('api.v1.products.filters', ['category' => 'shirts']))
            ->assertOk()
            ->assertJsonPath('meta.currency.display', 'IRT')
            ->json('data');

        expect($filters['total'])->toBe(2)
            ->and($filters['price'])->toBe(['min' => 1000, 'max' => 3000])
            ->and($filters['brands'])->toBe([['id' => $brand->id, 'name' => $brand->name, 'persian_name' => null, 'slug' => 'nike', 'count' => 2]])
            // Only the category's specifications, and only values products have.
            ->and($filters['attributes'])->toHaveCount(1)
            ->and($filters['attributes'][0]['values'])->toBe([['id' => $material->values[0]->id, 'value' => 'Cotton', 'slug' => 'cotton', 'count' => 2]])
            ->and($filters['options'][0]['values'])->toBe([[
                'id' => $color->values[0]->id, 'value' => 'Red', 'slug' => 'red', 'color_hex' => null, 'count' => 2,
            ]]);

        getJson(route('api.v1.products.filters', ['category' => 'unknown']))
            ->assertJsonPath('data.total', 0)
            ->assertJsonPath('data.price', null);
    });
});

describe('product page', function () {
    it('returns everything a product page needs, without hidden parts', function () {
        app(Settings::class)->set([Settings::PRICE_DISPLAY_CURRENCY => 'IRR']);
        $clothing = Category::factory()->create(['name' => 'Clothing', 'slug' => 'clothing']);
        $shirts = Category::factory()->childOf($clothing)->create(['name' => 'Shirts', 'slug' => 'shirts']);
        $hidden = Category::factory()->hidden()->create();
        $size = Option::factory()->withValues(['S' => 's', 'M' => 'm', 'L' => 'l'])->create(['slug' => 'size']);
        [$s, $m, $l] = $size->values->all();
        $material = Attribute::factory()->select(['Cotton' => 'cotton'])->create(['name' => 'Material', 'slug' => 'material']);
        $screen = Attribute::factory()->create(['name' => 'Width', 'slug' => 'width', 'type' => 'number', 'unit' => 'cm']);

        $product = Product::factory()->published()->create([
            'name' => 'Linen shirt', 'slug' => 'linen-shirt', 'type' => 'variable', 'description' => '<p>Soft</p>', 'low_stock_threshold' => 3,
        ]);
        $product->categories()->attach([$shirts->id => ['is_primary' => true], $hidden->id => ['is_primary' => false]]);
        $product->options()->attach($size, ['position' => 0]);
        $small = ProductVariant::factory()->for($product)->create(['sku' => 'LS-S', 'price' => 2000, 'sale_price' => 1500, 'sale_ends_at' => now()->addDay()]);
        $small->forceFill(['stock_quantity' => 2])->save();
        $small->optionValues()->attach($s);
        $medium = ProductVariant::factory()->for($product)->create(['sku' => 'LS-M', 'price' => 2000, 'position' => 1]);
        $medium->optionValues()->attach($m);
        ProductVariant::factory()->for($product)->create(['sku' => 'LS-L', 'is_enabled' => false])->optionValues()->attach($l);
        $product->attributeValues()->createMany([
            ['attribute_id' => $material->id, 'attribute_value_id' => $material->values[0]->id],
            ['attribute_id' => $screen->id, 'value_number' => 70.5],
        ]);

        $page = getJson(route('api.v1.products.show', ['slug' => 'linen-shirt']))
            ->assertOk()
            ->assertJsonPath('meta.currency', ['display' => 'IRR', 'rials_per_unit' => 1])
            ->json('data');

        expect($page['description'])->toBe('<p>Soft</p>')
            ->and(array_column($page['categories'], 'slug'))->toBe(['shirts'])
            ->and(array_column($page['breadcrumb'], 'slug'))->toBe(['clothing', 'shirts'])
            ->and(array_column($page['options'][0]['values'], 'slug'))->toBe(['s', 'm'])
            ->and(array_column($page['variants'], 'sku'))->toBe(['LS-S', 'LS-M'])
            ->and($page['variants'][0])->toMatchArray([
                'price' => 1500, 'regular_price' => 2000, 'discount_percent' => 25,
                'in_stock' => true, 'low_stock' => true, 'option_value_ids' => [$s->id],
            ])
            ->and($page['variants'][0]['sale_ends_at'])->not->toBeNull()
            ->and($page['variants'][1]['in_stock'])->toBeFalse()
            ->and($page['price'])->toBe(['min' => 1500, 'max' => 2000])
            ->and($page['specifications'])->toBe([
                ['name' => 'Material', 'slug' => 'material', 'type' => 'select', 'unit' => null, 'values' => ['Cotton']],
                ['name' => 'Width', 'slug' => 'width', 'type' => 'number', 'unit' => 'cm', 'values' => ['70.5']],
            ])
            ->and(json_encode($page))->not->toContain('stock_quantity')->not->toContain('focus_keyphrase');
    });
});

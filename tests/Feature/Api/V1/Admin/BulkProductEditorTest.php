<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Option;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

function bulkEditor(UserRole $role = UserRole::Admin): User
{
    return User::factory()->withRole($role)->create();
}

/**
 * A product in a category with one variant per entry; amounts are Rials.
 *
 * @param  list<array<string, mixed>>  $variants
 */
function bulkProduct(array $attributes = [], array $variants = [['price' => 100_000]]): Product
{
    $product = Product::factory()->create(['track_stock' => true, ...$attributes]);
    $product->categories()->attach(Category::factory()->create(), ['is_primary' => true]);

    foreach ($variants as $position => $variant) {
        $stock = $variant['stock'] ?? 10;
        unset($variant['stock']);
        ProductVariant::factory()->for($product)->create(['position' => $position, ...$variant])
            ->forceFill(['stock_quantity' => $stock])->save();
    }

    return $product->load('variants');
}

/**
 * @param  list<array<string, mixed>>  $variants
 */
function saveBulk(array $variants, ?User $actor = null): TestResponse
{
    return actingAs($actor ?? bulkEditor())->putJson(route('api.v1.admin.products.bulk.update'), ['variants' => $variants]);
}

it('is for staff who manage products', function () {
    $product = bulkProduct();

    actingAs(bulkEditor(UserRole::Author))->getJson(route('api.v1.admin.products.bulk.index'))->assertForbidden();
    saveBulk([['id' => $product->variants[0]->id, 'price' => 1]], bulkEditor(UserRole::Author))->assertForbidden();
});

describe('page', function () {
    it('lists products with every variant labelled in option order, never deleted ones', function () {
        $size = Option::factory()->withValues(['M' => 'm'])->create();
        $color = Option::factory()->withValues(['Red' => 'red'])->create();
        $shirt = bulkProduct(['type' => 'variable'], [['price' => 5000, 'sale_price' => 4000, 'stock' => 3]]);
        $shirt->options()->attach([$size->id => ['position' => 0], $color->id => ['position' => 1]]);
        $shirt->variants[0]->optionValues()->attach([$color->values[0]->id, $size->values[0]->id]);
        bulkProduct()->delete();

        $rows = actingAs(bulkEditor())->getJson(route('api.v1.admin.products.bulk.index', ['trashed' => 1]))
            ->assertOk()
            ->json('data');

        expect($rows)->toHaveCount(1)
            ->and($rows[0]['variants'][0])->toMatchArray([
                'label' => 'M · Red', 'price' => 5000, 'sale_price' => 4000, 'stock_quantity' => 3, 'is_enabled' => true, 'image' => null,
            ]);
    });

    it('filters by quick filter and counts each one with the other filters applied', function () {
        $brand = Brand::factory()->create();
        $out = bulkProduct(['brand_id' => $brand->id], [['price' => 10, 'stock' => 0]]);
        $sale = bulkProduct(['brand_id' => $brand->id], [['price' => 10, 'sale_price' => 5, 'stock' => 50]]);
        $inactive = bulkProduct(['brand_id' => $brand->id], [['price' => 10, 'stock' => 50], ['price' => 10, 'stock' => 50, 'is_enabled' => false]]);
        bulkProduct(variants: [['price' => 10, 'stock' => 0]]);
        $index = fn (array $query) => actingAs(bulkEditor())->getJson(route('api.v1.admin.products.bulk.index', $query))->assertOk();

        $index(['brand_id' => $brand->id])->assertJsonPath('meta.quick_counts', ['all' => 3, 'out' => 1, 'low' => 1, 'sale' => 1, 'inactive' => 1]);

        expect($index(['brand_id' => $brand->id, 'quick' => 'out'])->json('data.*.id'))->toBe([$out->id])
            ->and($index(['quick' => 'sale'])->json('data.*.id'))->toBe([$sale->id])
            ->and($index(['quick' => 'inactive'])->json('data.*.id'))->toBe([$inactive->id]);
    });
});

describe('save', function () {
    it('saves every changed variant, records stock as a bulk edit and logs each product once', function () {
        $shirt = bulkProduct(['name' => 'Shirt'], [['price' => 100_000, 'stock' => 5], ['price' => 100_000]]);
        $shoe = bulkProduct(variants: [['price' => 300_000]]);
        $editor = bulkEditor();

        saveBulk([
            ['id' => $shirt->variants[0]->id, 'price' => 110_000, 'stock_quantity' => 8, 'stock_quantity_was' => 5],
            ['id' => $shirt->variants[1]->id, 'is_enabled' => false],
            ['id' => $shoe->variants[0]->id, 'sale_price' => 250_000],
        ], $editor)
            ->assertOk()
            ->assertJsonPath('data.variant_ids', [$shirt->variants[0]->id, $shirt->variants[1]->id, $shoe->variants[0]->id]);

        expect($shirt->variants[0]->fresh()->only(['price', 'stock_quantity']))->toBe(['price' => 110_000, 'stock_quantity' => 8])
            ->and($shirt->variants[1]->fresh()->is_enabled)->toBeFalse()
            ->and($shoe->variants[0]->fresh()->sale_price)->toBe(250_000);

        assertDatabaseHas(StockMovement::class, [
            'product_variant_id' => $shirt->variants[0]->id, 'reason' => 'bulk_edit', 'quantity_before' => 5, 'quantity_after' => 8, 'user_id' => $editor->id,
        ]);
        expect(Activity::query()->where('event', 'product.updated')->pluck('subject_id')->sort()->values()->all())
            ->toBe([$shirt->id, $shoe->id]);
    });

    it('starts a new sale now without an end, keeps the dates of a changed sale and clears them when removed', function () {
        $product = bulkProduct(variants: [
            ['price' => 1000, 'sale_starts_at' => now()->subYear(), 'sale_ends_at' => now()->subMonth()],
            ['price' => 1000, 'sale_price' => 800, 'sale_ends_at' => now()->addWeek()],
            ['price' => 1000, 'sale_price' => 800, 'sale_ends_at' => now()->addWeek()],
        ]);
        [$noSale, $onSale, $removed] = $product->variants->all();

        saveBulk([
            ['id' => $noSale->id, 'sale_price' => 900],
            ['id' => $onSale->id, 'sale_price' => 700],
            ['id' => $removed->id, 'sale_price' => null],
        ])->assertOk();

        expect($noSale->fresh()->only(['sale_starts_at', 'sale_ends_at']))->toBe(['sale_starts_at' => null, 'sale_ends_at' => null])
            ->and($noSale->fresh()->isOnSale())->toBeTrue()
            ->and($onSale->fresh()->sale_ends_at)->not->toBeNull()
            ->and($removed->fresh()->only(['sale_price', 'sale_ends_at']))->toBe(['sale_price' => null, 'sale_ends_at' => null]);
    });

    it('saves nothing when any change is invalid and marks it', function (array $change, int $stock, string $field) {
        $valid = bulkProduct(variants: [['price' => 1000]]);
        $other = bulkProduct(variants: [['price' => 1000, 'sale_price' => 800, 'stock' => $stock]]);

        saveBulk([
            ['id' => $valid->variants[0]->id, 'price' => 2000],
            ['id' => $other->variants[0]->id, ...$change],
        ])->assertUnprocessable()->assertJsonValidationErrors(["variants.1.{$field}"]);

        expect($valid->variants[0]->fresh()->price)->toBe(1000)
            ->and(Activity::query()->count())->toBe(0);
    })->with([
        'a sale at or above the price' => [['sale_price' => 1000], 10, 'sale_price'],
        'a price at or below the sale' => [['price' => 800], 10, 'price'],
        'stock that changed meanwhile' => [['stock_quantity' => 5, 'stock_quantity_was' => 9], 4, 'stock_quantity'],
    ]);

    it('refuses stock for untracked products and variants of deleted products', function () {
        $untracked = bulkProduct(['track_stock' => false]);
        $deleted = bulkProduct();
        $deleted->delete();

        saveBulk([
            ['id' => $untracked->variants[0]->id, 'stock_quantity' => 3, 'stock_quantity_was' => 10],
            ['id' => $deleted->variants[0]->id, 'price' => 1],
        ])->assertUnprocessable()->assertJsonValidationErrors(['variants.0.stock_quantity', 'variants.1.id']);
    });

    it('keeps an enabled variant on published products', function () {
        $product = bulkProduct(['status' => 'published', 'published_at' => now()], [['price' => 1], ['price' => 1, 'is_enabled' => false]]);

        saveBulk([['id' => $product->variants[0]->id, 'is_enabled' => false]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['variants.0.is_enabled']);

        saveBulk([
            ['id' => $product->variants[0]->id, 'is_enabled' => false],
            ['id' => $product->variants[1]->id, 'is_enabled' => true],
        ])->assertOk();
    });

    it('does not log products whose values did not change', function () {
        $product = bulkProduct(variants: [['price' => 1000]]);

        saveBulk([['id' => $product->variants[0]->id, 'price' => 1000]])
            ->assertOk()
            ->assertJsonPath('data.variant_ids', []);

        expect(Activity::query()->count())->toBe(0);
    });
});

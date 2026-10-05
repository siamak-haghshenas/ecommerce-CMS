<?php

declare(strict_types=1);

use App\Models\Category;

use function Pest\Laravel\getJson;

it('returns the visible tree, nested and in order, without any login', function () {
    $clothing = Category::factory()->create(['position' => 1, 'name' => 'Clothing', 'slug' => 'clothing']);
    $books = Category::factory()->create(['position' => 0, 'name' => 'Books', 'slug' => 'books']);
    $shirts = Category::factory()->childOf($clothing, 1)->create(['name' => 'Shirts', 'slug' => 'shirts']);
    $hats = Category::factory()->childOf($clothing, 0)->hidden()->create();
    Category::factory()->childOf($hats)->create();
    Category::factory()->hidden()->create();

    getJson(route('api.v1.categories.index'))
        ->assertOk()
        ->assertExactJson(['data' => [
            ['id' => $books->id, 'name' => 'Books', 'slug' => 'books', 'image' => null, 'children' => []],
            ['id' => $clothing->id, 'name' => 'Clothing', 'slug' => 'clothing', 'image' => null, 'children' => [
                ['id' => $shirts->id, 'name' => 'Shirts', 'slug' => 'shirts', 'image' => null, 'children' => []],
            ]],
        ]]);
});

it('restores a hidden branch exactly as it was when the parent is shown again', function () {
    $clothing = Category::factory()->hidden()->create();
    $visibleChild = Category::factory()->childOf($clothing, 0)->create();
    Category::factory()->childOf($clothing, 1)->hidden()->create();

    getJson(route('api.v1.categories.index'))->assertJsonCount(0, 'data');

    $clothing->update(['is_active' => true]);

    getJson(route('api.v1.categories.index'))
        ->assertJsonPath('data.0.children.*.id', [$visibleChild->id]);
});

it('returns one category with SEO fields, breadcrumb and visible children', function () {
    $clothing = Category::factory()->create(['name' => 'Clothing', 'slug' => 'clothing']);
    $men = Category::factory()->childOf($clothing)->create([
        'name' => 'Men',
        'slug' => 'men',
        'description' => 'Everything for men.',
        'meta_title' => 'Men clothing',
        'meta_description' => 'Shop men clothing.',
    ]);
    $shirts = Category::factory()->childOf($men)->create(['name' => 'Shirts', 'slug' => 'shirts']);
    Category::factory()->childOf($men)->hidden()->create();

    getJson(route('api.v1.categories.show', ['slug' => 'men']))
        ->assertOk()
        ->assertExactJson(['data' => [
            'id' => $men->id,
            'name' => 'Men',
            'slug' => 'men',
            'description' => 'Everything for men.',
            'meta_title' => 'Men clothing',
            'meta_description' => 'Shop men clothing.',
            'image' => null,
            'breadcrumb' => [['id' => $clothing->id, 'name' => 'Clothing', 'slug' => 'clothing']],
            'children' => [['id' => $shirts->id, 'name' => 'Shirts', 'slug' => 'shirts', 'image' => null]],
        ]]);
});

it('returns 404 for hidden categories, categories under a hidden parent and unknown slugs', function (string $slug) {
    $hidden = Category::factory()->hidden()->create(['slug' => 'hidden']);
    Category::factory()->childOf($hidden)->create(['slug' => 'under-hidden']);

    getJson(route('api.v1.categories.show', ['slug' => $slug]))
        ->assertNotFound()
        ->assertJsonPath('code', 'not_found');
})->with(['hidden', 'under-hidden', 'unknown']);

it('keeps product and blog categories on their own endpoints, even with the same slug', function () {
    $product = Category::factory()->create(['slug' => 'news', 'name' => 'News (products)']);
    $blog = Category::factory()->blog()->create(['slug' => 'news', 'name' => 'News']);
    Category::factory()->blog()->childOf($blog)->create(['slug' => 'local']);

    getJson(route('api.v1.categories.index'))->assertJsonPath('data.*.id', [$product->id]);
    getJson(route('api.v1.blog.categories.index'))
        ->assertJsonPath('data.*.id', [$blog->id])
        ->assertJsonPath('data.0.children.0.slug', 'local');

    getJson(route('api.v1.categories.show', 'news'))->assertJsonPath('data.name', 'News (products)');
    getJson(route('api.v1.blog.categories.show', 'news'))->assertJsonPath('data.name', 'News');
    getJson(route('api.v1.blog.categories.show', 'local'))->assertJsonPath('data.breadcrumb.0.slug', 'news');
    getJson(route('api.v1.categories.show', 'local'))->assertNotFound();
});

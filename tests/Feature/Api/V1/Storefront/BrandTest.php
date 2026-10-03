<?php

declare(strict_types=1);

use App\Models\Brand;

use function Pest\Laravel\getJson;

it('lists visible brands in the manual order, without any login', function () {
    $second = Brand::factory()->create(['position' => 1, 'name' => 'Nike', 'slug' => 'nike']);
    $first = Brand::factory()->create(['position' => 0, 'name' => 'Adidas', 'slug' => 'adidas', 'website' => 'https://www.adidas.com']);
    Brand::factory()->hidden()->create(['position' => 2]);

    getJson(route('api.v1.brands.index'))
        ->assertOk()
        ->assertJsonPath('data.*.id', [$first->id, $second->id])
        ->assertJsonPath('data.0.website', 'https://www.adidas.com');
});

it('returns one brand with its storefront fields only', function () {
    $brand = Brand::factory()->create([
        'name' => 'Adidas',
        'persian_name' => 'آدیداس',
        'slug' => 'adidas',
        'description' => 'Sportswear.',
        'meta_title' => 'Adidas shoes and clothing',
        'meta_description' => 'Shop Adidas.',
        'focus_keyphrase' => 'adidas',
        'seo_score' => 80,
    ]);

    getJson(route('api.v1.brands.show', ['slug' => 'adidas']))
        ->assertOk()
        ->assertExactJson(['data' => [
            'id' => $brand->id,
            'name' => 'Adidas',
            'persian_name' => 'آدیداس',
            'slug' => 'adidas',
            'description' => 'Sportswear.',
            'website' => null,
            'logo' => null,
            'meta_title' => 'Adidas shoes and clothing',
            'meta_description' => 'Shop Adidas.',
        ]]);
});

it('returns 404 for hidden and unknown brands', function (string $slug) {
    Brand::factory()->hidden()->create(['slug' => 'hidden']);

    getJson(route('api.v1.brands.show', ['slug' => $slug]))
        ->assertNotFound()
        ->assertJsonPath('code', 'not_found');
})->with(['hidden', 'unknown']);

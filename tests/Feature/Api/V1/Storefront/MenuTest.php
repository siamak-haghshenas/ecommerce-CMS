<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Page;

use function Pest\Laravel\getJson;

it('returns the visible items in order, with what they link to', function () {
    $about = Page::factory()->create(['title' => 'About', 'slug' => 'about']);
    $shoes = Category::factory()->create(['name' => 'Shoes', 'slug' => 'shoes']);
    $top = MenuItem::factory()->forCategory($shoes)->create(['position' => 0]);
    MenuItem::factory()->create(['parent_id' => $top->id, 'label' => 'Sale', 'url' => '/sale', 'position' => 1]);
    MenuItem::factory()->forPage(Page::factory()->draft()->create())->create(['parent_id' => $top->id, 'position' => 0]);
    MenuItem::factory()->forPage($about)->create(['position' => 1, 'label' => 'Who we are', 'open_in_new_tab' => true]);
    MenuItem::factory()->create(['location' => 'footer', 'label' => 'Not here']);

    getJson(route('api.v1.menus.show', 'header'))
        ->assertOk()
        ->assertJsonPath('data.0', [
            'label' => 'Shoes',
            'type' => 'category',
            'url' => null,
            'page' => null,
            'category' => ['slug' => 'shoes', 'name' => 'Shoes'],
            'open_in_new_tab' => false,
            'children' => [[
                'label' => 'Sale', 'type' => 'url', 'url' => '/sale', 'page' => null, 'category' => null,
                'open_in_new_tab' => false, 'children' => [],
            ]],
        ])
        ->assertJsonPath('data.1.label', 'Who we are')
        ->assertJsonPath('data.1.page', ['slug' => 'about', 'title' => 'About'])
        ->assertJsonCount(2, 'data');
});

it('leaves out a hidden top item together with its sub-items', function () {
    $hidden = MenuItem::factory()->forCategory(Category::factory()->create(['is_active' => false]))->create();
    MenuItem::factory()->create(['parent_id' => $hidden->id]);
    $trashed = Page::factory()->create();
    MenuItem::factory()->forPage($trashed)->create(['position' => 1]);
    $trashed->delete();

    getJson(route('api.v1.menus.show', 'header'))->assertOk()->assertJsonCount(0, 'data');
});

it('returns 404 for an unknown menu', function () {
    getJson('/api/v1/menus/sidebar')->assertNotFound();
});

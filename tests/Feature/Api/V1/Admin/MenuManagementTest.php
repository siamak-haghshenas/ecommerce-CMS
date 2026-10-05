<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\getJson;
use function Pest\Laravel\travel;

function menuManager(UserRole $role = UserRole::Author): User
{
    return User::factory()->withRole($role)->create();
}

describe('access', function () {
    it('returns 401 to guests', function () {
        getJson(route('api.v1.admin.menus.show', 'header'))->assertUnauthorized();
    });

    it('lets authors and admins manage menus', function (UserRole $role) {
        actingAs(menuManager($role))->putJson(route('api.v1.admin.menus.update', 'footer'), ['items' => []])->assertOk();
    })->with([UserRole::Author, UserRole::Admin]);

    it('returns 403 to customers and 404 for unknown menus', function () {
        actingAs(menuManager(UserRole::Customer))->getJson(route('api.v1.admin.menus.show', 'header'))->assertForbidden();
        actingAs(menuManager())->getJson('/api/v1/admin/menus/sidebar')->assertNotFound();
    });
});

describe('saving', function () {
    it('saves the whole tree in order and shows why items are hidden', function () {
        $about = Page::factory()->create(['title' => 'About', 'slug' => 'about']);
        $draft = Page::factory()->draft()->create(['title' => 'Soon']);
        $shoes = Category::factory()->create(['name' => 'Shoes', 'slug' => 'shoes']);
        $hidden = Category::factory()->create(['is_active' => false]);

        actingAs(menuManager())->putJson(route('api.v1.admin.menus.update', 'header'), ['items' => [
            ['type' => 'category', 'category_id' => $shoes->id, 'children' => [
                ['type' => 'category', 'category_id' => $hidden->id],
                ['type' => 'url', 'label' => 'Sale', 'url' => '/sale', 'open_in_new_tab' => false],
            ]],
            ['type' => 'page', 'page_id' => $about->id, 'label' => 'Who we are'],
            ['type' => 'page', 'page_id' => $draft->id],
            ['type' => 'url', 'label' => 'Blog', 'url' => 'https://blog.example.com', 'open_in_new_tab' => true],
        ]])
            ->assertOk()
            ->assertJsonPath('data.*.display_label', ['Shoes', 'Who we are', 'Soon', 'Blog'])
            ->assertJsonPath('data.0.children.*.display_label', [$hidden->name, 'Sale'])
            ->assertJsonPath('data.0.children.0.hidden_reason', 'category_hidden')
            ->assertJsonPath('data.2.is_visible', false)
            ->assertJsonPath('data.2.hidden_reason', 'page_draft')
            ->assertJsonPath('data.3.open_in_new_tab', true);

        expect(MenuItem::query()->count())->toBe(6)
            ->and(Activity::query()->where('event', 'menu.updated')->sole()->properties['location'])->toBe('header');
    });

    it('updates items by id, deletes the ones left out and logs only real changes', function () {
        $manager = menuManager();
        $top = MenuItem::factory()->create(['label' => 'Old']);
        $child = MenuItem::factory()->create(['parent_id' => $top->id]);
        $gone = MenuItem::factory()->create(['position' => 1]);
        $footer = MenuItem::factory()->create(['location' => 'footer']);
        $save = fn (array $items) => actingAs($manager)->putJson(route('api.v1.admin.menus.update', 'header'), ['items' => $items])->assertOk();

        // The sub-item becomes a top item, before its old parent.
        $items = [
            ['id' => $child->id, 'type' => 'url', 'label' => $child->label, 'url' => $child->url],
            ['id' => $top->id, 'type' => 'url', 'label' => 'New', 'url' => $top->url],
        ];
        $save($items);

        expect(MenuItem::query()->where('location', 'header')->orderBy('position')->pluck('id')->all())->toBe([$child->id, $top->id])
            ->and($child->fresh()?->parent_id)->toBeNull()
            ->and($top->fresh()?->label)->toBe('New')
            ->and(MenuItem::query()->find($gone->id))->toBeNull()
            ->and(MenuItem::query()->find($footer->id))->not->toBeNull();

        $save($items);

        expect(Activity::query()->where('event', 'menu.updated')->count())->toBe(1);
    });

    it('refuses incomplete or unsafe items and a third level', function (array $item, string $error) {
        actingAs(menuManager())->putJson(route('api.v1.admin.menus.update', 'header'), ['items' => [$item]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors($error);
    })->with([
        'link without a label' => [['type' => 'url', 'url' => '/sale'], 'items.0.label'],
        'link without an address' => [['type' => 'url', 'label' => 'Sale'], 'items.0.url'],
        'script address' => [['type' => 'url', 'label' => 'x', 'url' => 'javascript:alert(1)'], 'items.0.url'],
        'protocol-relative address' => [['type' => 'url', 'label' => 'x', 'url' => '//evil.example'], 'items.0.url'],
        'page that does not exist' => [['type' => 'page', 'page_id' => 999999], 'items.0.page_id'],
        'category missing' => [['type' => 'category'], 'items.0.category_id'],
        'third level' => [['type' => 'url', 'label' => 'a', 'url' => '/a', 'children' => [
            ['type' => 'url', 'label' => 'b', 'url' => '/b', 'children' => [['type' => 'url', 'label' => 'c', 'url' => '/c']]],
        ]], 'items.0.children.0.children'],
    ]);
});

describe('linked pages and categories', function () {
    it('refuses to delete a category a menu links to', function () {
        $category = Category::factory()->create();
        MenuItem::factory()->forCategory($category)->create(['location' => 'footer']);

        actingAs(menuManager(UserRole::Admin))->deleteJson(route('api.v1.admin.categories.destroy', $category))
            ->assertConflict()
            ->assertJsonPath('code', 'category_in_menu');

        expect($category->fresh())->not->toBeNull();
    });

    it('hides items of pages in the trash, and removes them with the page', function () {
        $page = Page::factory()->create();
        $item = MenuItem::factory()->forPage($page)->create();
        $page->delete();

        actingAs(menuManager())->getJson(route('api.v1.admin.menus.show', 'header'))
            ->assertJsonPath('data.0.hidden_reason', 'page_trashed');

        travel(Page::TRASH_DAYS + 1)->days();
        artisan('model:prune', ['--model' => [Page::class]])->assertSuccessful();

        expect(MenuItem::query()->find($item->id))->toBeNull();
    });
});

describe('link targets', function () {
    it('lists pages and categories for authors, who have no access to those lists themselves', function () {
        $parent = Category::factory()->create(['name' => 'Clothes', 'is_active' => false]);
        Category::factory()->create(['name' => 'Shirts', 'parent_id' => $parent->id]);
        Page::factory()->draft()->create(['title' => 'Terms']);
        Page::factory()->create(['title' => 'About'])->delete();

        actingAs(menuManager())->getJson(route('api.v1.admin.menus.targets'))
            ->assertOk()
            ->assertJsonPath('data.pages.*.title', ['Terms'])
            ->assertJsonPath('data.pages.0.state', 'draft')
            ->assertJsonPath('data.categories.*.name', ['Clothes', 'Shirts'])
            ->assertJsonPath('data.categories.1.depth', 1)
            ->assertJsonPath('data.categories.1.is_visible', false);
    });
});

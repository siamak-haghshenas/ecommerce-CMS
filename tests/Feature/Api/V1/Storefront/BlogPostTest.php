<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;

use function Pest\Laravel\getJson;

it('lists published posts newest first, as cards, for any client', function () {
    $older = Post::factory()->create(['title' => 'Older', 'published_at' => now()->subDays(3)]);
    $newer = Post::factory()->create(['title' => 'Newer', 'published_at' => now()->subDay()]);
    Post::factory()->draft()->create();
    Post::factory()->scheduled()->create();
    Post::factory()->create()->delete();

    getJson(route('api.v1.blog.posts.index'))
        ->assertOk()
        ->assertJsonPath('data.*.id', [$newer->id, $older->id])
        ->assertJsonMissingPath('data.0.content')
        ->assertJsonPath('meta.total', 2);
});

it('filters by blog category with visible sub-categories, tag, author byline and words', function () {
    $parent = Category::factory()->blog()->create(['slug' => 'guides']);
    $child = Category::factory()->childOf($parent)->create();
    $hidden = Category::factory()->blog()->hidden()->create(['slug' => 'secret']);
    $writer = User::factory()->withRole(UserRole::Author)->create(['author_name' => 'Sara', 'author_slug' => 'sara']);
    $inChild = Post::factory()->create(['title' => 'Choosing running shoes', 'author_id' => $writer->id]);
    $inChild->categories()->attach($child->id, ['is_primary' => true]);
    $tagged = Post::factory()->create(['title' => 'Summer looks']);
    $tagged->tags()->attach(Tag::query()->create(['name' => 'Summer', 'slug' => 'summer'])->id);
    Post::factory()->create()->categories()->attach($hidden->id, ['is_primary' => true]);

    $list = fn (array $query) => getJson(route('api.v1.blog.posts.index', $query))->json('data.*.id');

    expect($list(['category' => 'guides']))->toBe([$inChild->id])
        ->and($list(['category' => 'secret']))->toBe([])
        ->and($list(['category' => 'unknown']))->toBe([])
        ->and($list(['tag' => 'summer']))->toBe([$tagged->id])
        ->and($list(['author' => 'sara']))->toBe([$inChild->id])
        ->and($list(['q' => 'running SHOES']))->toBe([$inChild->id]);
});

it('shows a byline only when the author has one, never the account name', function () {
    $withByline = User::factory()->withRole(UserRole::Author)->create(['name' => 'Private Name', 'author_name' => 'Sara', 'author_slug' => 'sara', 'author_bio' => 'Hi']);
    $without = User::factory()->withRole(UserRole::Author)->create(['name' => 'Another Private']);
    Post::factory()->create(['slug' => 'with', 'author_id' => $withByline->id]);
    Post::factory()->create(['slug' => 'without', 'author_id' => $without->id]);

    getJson(route('api.v1.blog.posts.show', 'with'))
        ->assertJsonPath('data.author', ['name' => 'Sara', 'slug' => 'sara', 'bio' => 'Hi', 'avatar' => null])
        ->assertDontSee('Private Name');
    getJson(route('api.v1.blog.posts.show', 'without'))
        ->assertJsonPath('data.author', null)
        ->assertDontSee('Another Private');
});

it('returns a post with visible categories, its breadcrumb, tags and the seo block', function () {
    $parent = Category::factory()->blog()->create(['slug' => 'guides', 'name' => 'Guides']);
    $primary = Category::factory()->childOf($parent)->create(['slug' => 'shoes', 'name' => 'Shoes']);
    $hidden = Category::factory()->blog()->hidden()->create();
    $post = Post::factory()->create(['slug' => 'shoe-guide', 'title' => 'Shoe guide', 'excerpt' => 'How to choose', 'focus_keyphrase' => 'secret']);
    $post->categories()->attach([$primary->id => ['is_primary' => true], $hidden->id => ['is_primary' => false]]);
    $post->tags()->attach(Tag::query()->create(['name' => 'Summer', 'slug' => 'summer'])->id);

    getJson(route('api.v1.blog.posts.show', 'shoe-guide'))
        ->assertOk()
        ->assertJsonPath('data.categories', [['slug' => 'shoes', 'name' => 'Shoes', 'is_primary' => true]])
        ->assertJsonPath('data.breadcrumb.*.slug', ['guides', 'shoes'])
        ->assertJsonPath('data.tags', [['slug' => 'summer', 'name' => 'Summer']])
        ->assertJsonPath('data.seo.title', 'Shoe guide')
        ->assertJsonPath('data.seo.og.description', 'How to choose')
        ->assertJsonMissingPath('data.focus_keyphrase');
});

it('returns 404 for draft, scheduled, trashed and unknown posts', function (string $state) {
    $post = match ($state) {
        'draft' => Post::factory()->draft()->create(),
        'scheduled' => Post::factory()->scheduled()->create(),
        'trashed' => tap(Post::factory()->create())->delete(),
        default => null,
    };

    getJson(route('api.v1.blog.posts.show', $post?->slug ?? 'missing'))->assertNotFound();
})->with(['draft', 'scheduled', 'trashed', 'unknown']);

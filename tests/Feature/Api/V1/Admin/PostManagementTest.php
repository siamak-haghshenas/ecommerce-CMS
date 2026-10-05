<?php

declare(strict_types=1);

use App\Enums\MediaUsageRole;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\MediaUsage;
use App\Models\Post;
use App\Models\PostRevision;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\getJson;
use function Pest\Laravel\travel;

beforeEach(function () {
    Storage::fake('public');
});

function postWriter(UserRole $role = UserRole::Author): User
{
    return User::factory()->withRole($role)->create();
}

/**
 * @return array<string, mixed>
 */
function postPayload(array $overrides = []): array
{
    return [
        'title' => 'راهنمای انتخاب کفش',
        'slug' => 'shoe-guide',
        'excerpt' => null,
        'content' => '<p>How to choose.</p>',
        'status' => 'published',
        'published_at' => null,
        'category_ids' => [],
        'tags' => [],
        'meta_title' => null,
        'meta_description' => null,
        'focus_keyphrase' => null,
        'image_id' => null,
        ...$overrides,
    ];
}

describe('access', function () {
    it('returns 401 to guests and 403 to customers', function () {
        getJson(route('api.v1.admin.posts.index'))->assertUnauthorized();
        actingAs(postWriter(UserRole::Customer))->getJson(route('api.v1.admin.posts.index'))->assertForbidden();
    });

    it('lets authors and admins write posts', function (UserRole $role) {
        actingAs(postWriter($role))->postJson(route('api.v1.admin.posts.store'), postPayload())->assertCreated();
    })->with([UserRole::Author, UserRole::Admin]);
});

describe('saving', function () {
    it('creates a post with blog categories, shared tags, its writer as author, a revision and an audit entry', function () {
        $author = postWriter();
        $news = Category::factory()->blog()->create();
        $guides = Category::factory()->blog()->create();
        $existing = Tag::query()->create(['name' => 'هدیه', 'slug' => 'gift']);

        $response = actingAs($author)->postJson(route('api.v1.admin.posts.store'), postPayload([
            'content' => '<p onclick="x()">Hi</p><script>alert(1)</script>',
            'category_ids' => [$news->id, $guides->id],
            'primary_category_id' => $guides->id,
            // «هديه» typed with an Arabic ي matches the existing tag.
            'tags' => ['هديه', 'تابستان'],
        ]))->assertCreated();

        $post = Post::query()->sole();

        $response->assertJsonPath('data.content', '<p>Hi</p>')
            ->assertJsonPath('data.author.id', $author->id)
            ->assertJsonPath('data.categories.*.is_primary', [false, true])
            ->assertJsonPath('data.tags', ['تابستان', 'هدیه']);

        expect($post->tags()->pluck('tags.id'))->toContain($existing->id)
            ->and($post->revisions()->count())->toBe(1)
            ->and(Activity::query()->where('event', 'post.created')->sole()->subject_id)->toBe($post->id);
    });

    it('refuses product categories, a primary outside the categories and a customer as author', function (array $overrides, string $error) {
        $overrides = array_map(fn (mixed $value) => $value instanceof Closure ? $value() : $value, $overrides);

        actingAs(postWriter())->postJson(route('api.v1.admin.posts.store'), postPayload($overrides))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($error);
    })->with([
        'product category' => [['category_ids' => fn () => [Category::factory()->create()->id]], 'category_ids.0'],
        'primary not chosen' => [['category_ids' => fn () => [Category::factory()->blog()->create()->id], 'primary_category_id' => 999], 'primary_category_id'],
        'customer as author' => [['author_id' => fn () => User::factory()->withRole(UserRole::Customer)->create()->id], 'author_id'],
    ]);

    it('logs changed categories and tags, and keeps no revision when only they change', function () {
        $writer = postWriter();
        $post = Post::factory()->create(['slug' => 'shoe-guide']);
        $category = Category::factory()->blog()->create();

        // A first save keeps a version to compare with.
        actingAs($writer)->putJson(route('api.v1.admin.posts.update', $post), postPayload(['title' => 'Edited', 'content' => $post->content]))->assertOk();
        $revisions = $post->revisions()->count();

        actingAs($writer)->putJson(route('api.v1.admin.posts.update', $post), postPayload([
            'title' => 'Edited', 'content' => $post->content, 'category_ids' => [$category->id], 'tags' => ['new'],
        ]))->assertOk();

        expect($post->revisions()->count())->toBe($revisions)
            ->and(Activity::query()->where('event', 'post.updated')->latest('id')->first()?->properties['fields'])->toBe(['categories', 'tags']);
    });

    it('records library files of the content and the images as used', function () {
        $photo = libraryImage();
        $social = libraryImage('social.jpg');

        actingAs(postWriter())->postJson(route('api.v1.admin.posts.store'), postPayload([
            'content' => "<p>x</p><img data-media-id=\"{$photo->id}\">",
            'og_image_id' => $social->id,
        ]))->assertCreated();

        expect(MediaUsage::query()->orderBy('media_asset_id')->pluck('role')->all())->toBe([MediaUsageRole::Content, MediaUsageRole::OgImage]);
    });
});

describe('list', function () {
    it('filters by category with its sub-categories, tag, author and status', function () {
        $writer = postWriter();
        $parent = Category::factory()->blog()->create();
        $child = Category::factory()->childOf($parent)->create();
        $inChild = Post::factory()->create(['title' => 'In child', 'author_id' => $writer->id]);
        $inChild->categories()->attach($child->id, ['is_primary' => true]);
        $tagged = Post::factory()->draft()->create(['title' => 'Tagged']);
        $tagged->tags()->attach(Tag::query()->create(['name' => 'Summer', 'slug' => 'summer'])->id);

        $list = fn (array $query) => actingAs($writer)->getJson(route('api.v1.admin.posts.index', $query))->json('data.*.title');

        expect($list(['category_id' => $parent->id]))->toBe(['In child'])
            ->and($list(['tag_id' => Tag::query()->sole()->id]))->toBe(['Tagged'])
            ->and($list(['author_id' => $writer->id]))->toBe(['In child'])
            ->and($list(['status' => 'draft']))->toBe(['Tagged']);
    });

    it('lists the staff who can be authors', function () {
        $writer = postWriter();
        $writer->update(['author_name' => 'Sara', 'author_slug' => 'sara']);
        User::factory()->withRole(UserRole::Customer)->create();

        actingAs($writer)->getJson(route('api.v1.admin.posts.authors'))
            ->assertOk()
            ->assertJsonPath('data.*.id', [$writer->id])
            ->assertJsonPath('data.0.has_byline', true);
    });
});

describe('trash and categories', function () {
    it('refuses deleting a blog category with posts, even trashed ones, until they are removed for good', function () {
        $category = Category::factory()->blog()->create();
        $post = Post::factory()->create();
        $post->categories()->attach($category->id, ['is_primary' => true]);
        $post->delete();
        $admin = postWriter(UserRole::Admin);

        actingAs($admin)->deleteJson(route('api.v1.admin.categories.destroy', $category))
            ->assertConflict()
            ->assertJsonPath('code', 'category_has_posts');

        travel(Post::TRASH_DAYS + 1)->days();
        artisan('model:prune', ['--model' => [Post::class]])->assertSuccessful();

        expect(PostRevision::query()->count())->toBe(0);
        actingAs($admin)->deleteJson(route('api.v1.admin.categories.destroy', $category))->assertNoContent();
    });

    it('moves to the trash and restores', function () {
        $writer = postWriter();
        $post = Post::factory()->create();

        actingAs($writer)->deleteJson(route('api.v1.admin.posts.destroy', $post))->assertNoContent();
        actingAs($writer)->getJson(route('api.v1.admin.posts.index', ['trashed' => 1]))->assertJsonPath('data.0.id', $post->id);
        actingAs($writer)->postJson(route('api.v1.admin.posts.restore', $post))->assertOk()->assertJsonPath('data.deleted_at', null);
    });
});

describe('tags', function () {
    it('lets post writers list tags, with product and post counts', function () {
        $tag = Tag::query()->create(['name' => 'Summer', 'slug' => 'summer']);
        Post::factory()->create()->tags()->attach($tag->id);

        actingAs(postWriter())->getJson(route('api.v1.admin.tags.index'))
            ->assertOk()
            ->assertJsonPath('data.0.posts_count', 1)
            ->assertJsonPath('data.0.products_count', 0);
    });

    it('moves posts too when tags are merged', function () {
        $from = Tag::query()->create(['name' => 'Sumer', 'slug' => 'sumer']);
        $into = Tag::query()->create(['name' => 'Summer', 'slug' => 'summer']);
        $post = Post::factory()->create();
        $post->tags()->attach($from->id);

        actingAs(postWriter(UserRole::Admin))->postJson(route('api.v1.admin.tags.merge', $from), ['into_tag_id' => $into->id])->assertOk();

        expect($post->tags()->pluck('tags.id')->all())->toBe([$into->id]);
    });
});

describe('autosave and preview', function () {
    it('keeps the unsaved form, offers it back, and previews it on the storefront', function () {
        $writer = postWriter();
        $post = Post::factory()->draft()->create(['title' => 'Draft']);

        travel(1)->minutes();
        actingAs($writer)->putJson(route('api.v1.admin.posts.autosave.update', $post), ['title' => 'Draft (typing)'])->assertOk();
        actingAs($writer)->getJson(route('api.v1.admin.posts.show', $post))->assertJsonPath('data.autosave.title', 'Draft (typing)');

        $token = actingAs($writer)->postJson(route('api.v1.admin.posts.preview', $post))->assertCreated()->json('data.token');

        getJson(route('api.v1.blog.posts.preview', $token))->assertOk()->assertJsonPath('data.title', 'Draft (typing)');
        // A post token never opens a page.
        getJson(route('api.v1.pages.preview', $token))->assertNotFound();
    });
});

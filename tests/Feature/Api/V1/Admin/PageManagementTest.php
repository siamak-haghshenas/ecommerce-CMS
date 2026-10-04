<?php

declare(strict_types=1);

use App\Enums\MediaUsageRole;
use App\Enums\PageStatus;
use App\Enums\UserRole;
use App\Models\MediaUsage;
use App\Models\Page;
use App\Models\PageRevision;
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

function pageManager(UserRole $role = UserRole::Author): User
{
    return User::factory()->withRole($role)->create();
}

/**
 * @return array<string, mixed>
 */
function pagePayload(array $overrides = []): array
{
    return [
        'title' => 'درباره ما',
        'slug' => 'about-us',
        'excerpt' => null,
        'content' => '<h2>Who we are</h2><p>A small shop.</p>',
        'status' => 'published',
        'published_at' => null,
        'meta_title' => null,
        'meta_description' => null,
        'focus_keyphrase' => null,
        'image_id' => null,
        ...$overrides,
    ];
}

describe('access', function () {
    it('returns 401 to guests', function () {
        getJson(route('api.v1.admin.pages.index'))->assertUnauthorized();
    });

    it('lets admins and authors manage pages', function (UserRole $role) {
        actingAs(pageManager($role))->postJson(route('api.v1.admin.pages.store'), pagePayload())->assertCreated();
    })->with([UserRole::Admin, UserRole::Author]);

    it('returns 403 to customers', function () {
        actingAs(pageManager(UserRole::Customer))->getJson(route('api.v1.admin.pages.index'))->assertForbidden();
    });
});

describe('saving', function () {
    it('creates a page with sanitized content, a score, a revision and an audit entry', function () {
        $author = pageManager();
        $image = libraryImage();

        $response = actingAs($author)->postJson(route('api.v1.admin.pages.store'), pagePayload([
            'slug' => ' About-Us ',
            'content' => '<p onclick="x()">Hello</p><script>alert(1)</script>',
            'image_id' => $image->id,
            'focus_keyphrase' => 'shop',
        ]))->assertCreated();

        $page = Page::query()->sole();

        $response->assertJsonPath('data.slug', 'about-us')
            ->assertJsonPath('data.content', '<p>Hello</p>')
            ->assertJsonPath('data.state', 'published')
            ->assertJsonPath('data.image.id', $image->id)
            ->assertJsonPath('data.created_by.id', $author->id)
            ->assertJsonPath('data.updated_by.id', $author->id);

        expect($page->published_at)->not->toBeNull()
            ->and($page->seo_score)->not->toBeNull()
            ->and($page->revisions()->sole()->only(['title', 'image_id', 'saved_by']))->toBe(['title' => 'درباره ما', 'image_id' => $image->id, 'saved_by' => $author->id])
            ->and(Activity::query()->where('event', 'page.created')->sole()->subject_id)->toBe($page->id);
    });

    it('refuses a slug taken by a page in the trash', function () {
        Page::factory()->create(['slug' => 'about-us'])->delete();

        actingAs(pageManager())->postJson(route('api.v1.admin.pages.store'), pagePayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slug');
    });

    it('logs the changed fields and keeps a revision only when the saved version changes', function () {
        $admin = pageManager(UserRole::Admin);
        $page = Page::factory()->create(['slug' => 'about-us']);
        $update = fn (array $changes) => actingAs($admin)->putJson(route('api.v1.admin.pages.update', $page), pagePayload($changes))->assertOk();

        $update(['title' => 'About']);
        $update(['title' => 'About', 'status' => 'draft']);
        $update(['title' => 'About', 'status' => 'draft']);

        expect($page->revisions()->count())->toBe(1)
            ->and(Activity::query()->where('event', 'page.updated')->pluck('properties')->map(fn ($properties) => $properties['fields'])->all())
            ->toBe([['title', 'content'], ['status', 'published_at']]);
    });

    it('keeps only the last 20 revisions', function () {
        $admin = pageManager();
        $page = Page::factory()->create(['slug' => 'about-us']);

        foreach (range(1, 22) as $number) {
            actingAs($admin)->putJson(route('api.v1.admin.pages.update', $page), pagePayload(['title' => "Version {$number}"]))->assertOk();
        }

        expect($page->revisions()->count())->toBe(Page::REVISIONS_KEPT)
            ->and($page->revisions()->first()?->title)->toBe('Version 22');
    });

    it('marks library files in the content as used and frees them when removed', function () {
        $admin = pageManager();
        $photo = libraryImage();

        actingAs($admin)->postJson(route('api.v1.admin.pages.store'), pagePayload([
            'content' => "<p>Look</p><img data-media-id=\"{$photo->id}\" src=\"https://evil.test/x.png\">",
        ]))->assertCreated()->assertJsonPath('data.content', fn (string $html): bool => ! str_contains($html, 'evil.test'));

        $page = Page::query()->sole();
        expect(MediaUsage::query()->where('media_asset_id', $photo->id)->sole()->role)->toBe(MediaUsageRole::Content);

        actingAs($admin)->getJson(route('api.v1.admin.media.show', $photo))
            ->assertJsonPath('data.usages.0.type', 'page')
            ->assertJsonPath('data.usages.0.name', 'درباره ما');

        actingAs($admin)->putJson(route('api.v1.admin.pages.update', $page), pagePayload(['content' => '<p>Text only</p>']))->assertOk();

        expect(MediaUsage::query()->count())->toBe(0);
    });
});

describe('revisions', function () {
    it('lists revisions newest first and shows one with every saved field', function () {
        $admin = pageManager();
        $image = libraryImage();
        $page = Page::factory()->create(['slug' => 'about-us']);
        actingAs($admin)->putJson(route('api.v1.admin.pages.update', $page), pagePayload(['title' => 'First', 'image_id' => $image->id]))->assertOk();
        actingAs($admin)->putJson(route('api.v1.admin.pages.update', $page), pagePayload(['title' => 'Second']))->assertOk();

        $first = $page->revisions()->get()->last();

        actingAs($admin)->getJson(route('api.v1.admin.pages.revisions.index', $page))
            ->assertOk()
            ->assertJsonPath('data.*.title', ['Second', 'First'])
            ->assertJsonMissingPath('data.0.content');

        actingAs($admin)->getJson(route('api.v1.admin.pages.revisions.show', [$page, $first]))
            ->assertOk()
            ->assertJsonPath('data.title', 'First')
            ->assertJsonPath('data.slug', 'about-us')
            ->assertJsonPath('data.image.id', $image->id)
            ->assertJsonPath('data.saved_by.id', $admin->id);

        // The image of an old revision may since have gone to the trash.
        $image->delete();
        actingAs($admin)->getJson(route('api.v1.admin.pages.revisions.show', [$page, $first]))->assertJsonPath('data.image', null);
    });

    it('returns 404 for a revision of another page', function () {
        $page = Page::factory()->create();
        $other = PageRevision::query()->create(['page_id' => Page::factory()->create()->id, 'title' => 'x', 'slug' => 'x']);

        actingAs(pageManager())->getJson(route('api.v1.admin.pages.revisions.show', [$page, $other]))->assertNotFound();
    });
});

describe('trash', function () {
    it('moves a page to the trash and restores it', function () {
        $admin = pageManager();
        $page = Page::factory()->create();

        actingAs($admin)->deleteJson(route('api.v1.admin.pages.destroy', $page))->assertNoContent();

        actingAs($admin)->getJson(route('api.v1.admin.pages.index'))->assertJsonCount(0, 'data');
        actingAs($admin)->getJson(route('api.v1.admin.pages.index', ['trashed' => 1]))->assertJsonPath('data.0.id', $page->id);
        actingAs($admin)->putJson(route('api.v1.admin.pages.update', $page), pagePayload())->assertNotFound();

        actingAs($admin)->postJson(route('api.v1.admin.pages.restore', $page))->assertOk()->assertJsonPath('data.deleted_at', null);

        expect(Activity::query()->whereIn('event', ['page.deleted', 'page.restored'])->pluck('event')->all())->toBe(['page.deleted', 'page.restored']);
    });

    it('removes pages for good after 30 days in the trash, freeing their files', function () {
        $image = libraryImage();
        $page = Page::factory()->create();
        $page->syncMediaAssets(MediaUsageRole::Image, [$image->id]);
        PageRevision::query()->create(['page_id' => $page->id, 'title' => 'x', 'slug' => 'x']);
        $page->delete();

        travel(29)->days();
        artisan('model:prune', ['--model' => [Page::class]])->assertSuccessful();
        expect(Page::withTrashed()->count())->toBe(1);

        travel(2)->days();
        artisan('model:prune', ['--model' => [Page::class]])->assertSuccessful();

        expect(Page::withTrashed()->count())->toBe(0)
            ->and(PageRevision::query()->count())->toBe(0)
            ->and(MediaUsage::query()->count())->toBe(0);
    });
});

describe('list', function () {
    it('filters by state and search', function (string $state, string $expected) {
        Page::factory()->draft()->create(['title' => 'Draft page']);
        Page::factory()->scheduled()->create(['title' => 'Scheduled page']);
        Page::factory()->create(['title' => 'Live page', 'status' => PageStatus::Published]);

        actingAs(pageManager())->getJson(route('api.v1.admin.pages.index', ['status' => $state]))
            ->assertOk()
            ->assertJsonPath('data.*.title', [$expected])
            ->assertJsonPath('data.0.state', $state);
    })->with([
        ['draft', 'Draft page'],
        ['scheduled', 'Scheduled page'],
        ['published', 'Live page'],
    ]);

    it('searches titles and slugs', function () {
        Page::factory()->create(['title' => 'Terms and conditions', 'slug' => 'terms']);
        Page::factory()->create(['title' => 'Privacy', 'slug' => 'privacy-policy']);

        actingAs(pageManager())->getJson(route('api.v1.admin.pages.index', ['q' => 'policy']))->assertJsonPath('data.*.slug', ['privacy-policy']);
    });
});

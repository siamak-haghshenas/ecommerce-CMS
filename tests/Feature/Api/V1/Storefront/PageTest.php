<?php

declare(strict_types=1);

use App\Enums\MediaUsageRole;
use App\Enums\UserRole;
use App\Models\Page;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\travel;

it('lists published pages only, without content, for any client', function () {
    $terms = Page::factory()->create(['title' => 'Terms', 'slug' => 'terms']);
    $about = Page::factory()->create(['title' => 'About', 'slug' => 'about']);
    Page::factory()->draft()->create();
    Page::factory()->scheduled()->create();
    Page::factory()->create()->delete();

    getJson(route('api.v1.pages.index'))
        ->assertOk()
        ->assertJsonPath('data.*.id', [$about->id, $terms->id])
        ->assertJsonMissingPath('data.0.content');
});

it('returns a published page with its storefront fields only', function () {
    Storage::fake('public');
    $image = libraryImage();
    $page = Page::factory()->create(['slug' => 'about', 'content' => '<p>Hi</p>', 'focus_keyphrase' => 'secret', 'meta_title' => 'About us']);
    $page->syncMediaAssets(MediaUsageRole::Image, [$image->id]);

    getJson(route('api.v1.pages.show', 'about'))
        ->assertOk()
        ->assertJsonPath('data.content', '<p>Hi</p>')
        ->assertJsonPath('data.meta_title', 'About us')
        ->assertJsonPath('data.image.id', $image->id)
        ->assertJsonMissingPath('data.focus_keyphrase')
        ->assertJsonMissingPath('data.seo_score');
});

it('returns 404 for draft, scheduled, trashed and unknown pages', function (string $state) {
    $page = match ($state) {
        'draft' => Page::factory()->draft()->create(),
        'scheduled' => Page::factory()->scheduled()->create(),
        'trashed' => tap(Page::factory()->create())->delete(),
        default => null,
    };

    getJson(route('api.v1.pages.show', $page?->slug ?? 'missing'))->assertNotFound();
})->with(['draft', 'scheduled', 'trashed', 'unknown']);

it('returns the social fields as typed, and an seo block with the fallbacks applied', function () {
    Storage::fake('public');
    $image = libraryImage();
    $page = Page::factory()->create(['title' => 'About', 'slug' => 'about', 'excerpt' => 'Who we are', 'robots_noindex' => true]);
    $page->syncMediaAssets(MediaUsageRole::Image, [$image->id]);

    getJson(route('api.v1.pages.show', 'about'))
        ->assertJsonPath('data.og_title', null)
        ->assertJsonPath('data.og_image', null)
        ->assertJsonPath('data.seo.title', 'About')
        ->assertJsonPath('data.seo.description', 'Who we are')
        ->assertJsonPath('data.seo.robots_noindex', true)
        ->assertJsonPath('data.seo.og.title', 'About')
        ->assertJsonPath('data.seo.og.description', 'Who we are')
        ->assertJsonPath('data.seo.og.image.id', $image->id);

    $social = libraryImage('social.jpg');
    $page->update(['meta_title' => 'About us', 'og_description' => 'Meet the team', 'canonical_url' => 'https://example.com/about']);
    $page->syncMediaAssets(MediaUsageRole::OgImage, [$social->id]);

    getJson(route('api.v1.pages.show', 'about'))
        ->assertJsonPath('data.seo.title', 'About us')
        ->assertJsonPath('data.seo.og.title', 'About us')
        ->assertJsonPath('data.seo.og.description', 'Meet the team')
        ->assertJsonPath('data.seo.og.image.id', $social->id)
        ->assertJsonPath('data.seo.canonical_url', 'https://example.com/about');

    getJson(route('api.v1.pages.index'))->assertJsonPath('data.0.robots_noindex', true);
});

describe('preview', function () {
    function previewToken(Page $page, User $staff): string
    {
        return actingAs($staff)->postJson(route('api.v1.admin.pages.preview', $page))->json('data.token');
    }

    it('shows a draft to anyone holding the token, never cached or indexed', function () {
        $page = Page::factory()->draft()->create(['title' => 'Coming soon', 'content' => '<p>Soon</p>']);
        $token = previewToken($page, User::factory()->withRole(UserRole::Author)->create());

        getJson(route('api.v1.pages.preview', $token))
            ->assertOk()
            ->assertJsonPath('data.title', 'Coming soon')
            ->assertJsonPath('data.content', '<p>Soon</p>')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertHeader('Cache-Control', 'no-store, private');
    });

    it('shows the staff member’s newer unsaved changes, not someone else’s', function () {
        $page = Page::factory()->create(['title' => 'About', 'content' => '<p>Saved</p>']);
        $author = User::factory()->withRole(UserRole::Author)->create();
        $other = User::factory()->withRole(UserRole::Author)->create();

        travel(1)->minutes();
        actingAs($author)->putJson(route('api.v1.admin.pages.autosave.update', $page), ['title' => 'About (new)', 'content' => '<p>Unsaved</p>'])->assertOk();

        getJson(route('api.v1.pages.preview', previewToken($page, $author)))
            ->assertJsonPath('data.title', 'About (new)')
            ->assertJsonPath('data.content', '<p>Unsaved</p>');

        getJson(route('api.v1.pages.preview', previewToken($page, $other)))->assertJsonPath('data.title', 'About');
        expect($page->fresh()?->title)->toBe('About');
    });

    it('returns 404 for expired, altered or unknown tokens and for pages in the trash', function () {
        $page = Page::factory()->draft()->create();
        $token = previewToken($page, User::factory()->withRole(UserRole::Author)->create());

        getJson(route('api.v1.pages.preview', 'not-a-token'))->assertNotFound();
        getJson(route('api.v1.pages.preview', substr($token, 0, -2).'xx'))->assertNotFound();

        travel(61)->minutes();
        getJson(route('api.v1.pages.preview', $token))->assertNotFound();

        travel(-61)->minutes();
        $page->delete();
        getJson(route('api.v1.pages.preview', $token))->assertNotFound();
    });
});

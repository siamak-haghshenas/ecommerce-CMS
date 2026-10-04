<?php

declare(strict_types=1);

use App\Enums\MediaUsageRole;
use App\Models\Page;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\getJson;

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

<?php

declare(strict_types=1);

namespace App\Actions\Pages;

use App\Enums\MediaType;
use App\Models\MediaAsset;
use App\Models\Page;
use App\Support\PreviewToken;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class ResolvePagePreviewAction
{
    /**
     * The page a token shows, whatever its status (not when in the trash), with the staff member's
     * autosave applied when it is newer. Nothing is saved.
     *
     * @throws ModelNotFoundException<Page> for invalid or expired tokens
     */
    public function handle(string $token): Page
    {
        $claims = PreviewToken::read($token, PreviewToken::PAGE) ?? throw (new ModelNotFoundException)->setModel(Page::class);
        $page = Page::query()->with(['imageAssets.media', 'ogImageAssets.media'])->findOrFail($claims['id']);
        $autosave = $page->autosaves()->where('user_id', $claims['user_id'])->first();

        if ($autosave === null || ! $autosave->isNewerThan($page)) {
            return $page;
        }

        $page->forceFill(array_filter(
            $autosave->only(['title', 'slug', 'excerpt', 'content', 'meta_title', 'meta_description', 'robots_noindex', 'canonical_url', 'og_title', 'og_description']),
            fn (mixed $value, string $key): bool => $value !== null || ! in_array($key, ['title', 'slug'], true),
            ARRAY_FILTER_USE_BOTH,
        ));

        $images = MediaAsset::query()
            ->whereKey(array_filter([$autosave->image_id, $autosave->og_image_id]))
            ->where('type', MediaType::Image)
            ->with('media')
            ->get();
        $page->setRelation('imageAssets', $images->where('id', $autosave->image_id)->values());
        $page->setRelation('ogImageAssets', $images->where('id', $autosave->og_image_id)->values());

        return $page;
    }
}

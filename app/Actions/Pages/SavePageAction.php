<?php

declare(strict_types=1);

namespace App\Actions\Pages;

use App\Actions\Seo\ScoreSeoAction;
use App\Data\Pages\PageData;
use App\Enums\MediaUsageRole;
use App\Enums\PageStatus;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\User;
use App\Support\RichText;
use Illuminate\Support\Facades\DB;

final readonly class SavePageAction
{
    public function __construct(private ScoreSeoAction $scoreSeo) {}

    /**
     * Create ($page null) or update a page, then keep a revision of what was saved.
     */
    public function handle(User $actor, ?Page $page, PageData $data): Page
    {
        return DB::transaction(function () use ($actor, $page, $data): Page {
            $creating = $page === null;
            $page ??= new Page;
            $previousKeyphrase = $page->focus_keyphrase;

            $page->fill([
                'title' => $data->title,
                'slug' => $data->slug,
                'excerpt' => $data->excerpt,
                'content' => $data->content,
                'status' => $data->status,
                'published_at' => $data->publishedAt ?? ($data->status === PageStatus::Published ? ($page->published_at ?? now()) : null),
                'meta_title' => $data->metaTitle,
                'meta_description' => $data->metaDescription,
                'focus_keyphrase' => $data->focusKeyphrase,
                'robots_noindex' => $data->robotsNoindex,
                'canonical_url' => $data->canonicalUrl,
                'og_title' => $data->ogTitle,
                'og_description' => $data->ogDescription,
            ]);

            $changedFields = array_keys($page->getDirty());

            if ($creating) {
                $page->created_by = $actor->id;
                $page->save();
            }

            if ($page->syncMediaAssets(MediaUsageRole::Image, $data->imageId === null ? [] : [$data->imageId])) {
                $changedFields[] = 'image';
            }

            if ($page->syncMediaAssets(MediaUsageRole::OgImage, $data->ogImageId === null ? [] : [$data->ogImageId])) {
                $changedFields[] = 'og_image';
            }

            // Library files shown in the content count as used, so they can't be deleted meanwhile.
            $page->syncMediaAssets(MediaUsageRole::Content, RichText::mediaIds($page->content));

            // What the saver had autosaved is now either saved or deliberately left out.
            $page->autosaves()->where('user_id', $actor->id)->delete();

            if (! $creating && $changedFields === []) {
                return $page;
            }

            $page->updated_by = $actor->id;
            $page->save();

            $this->scoreSeo->handle($page, $previousKeyphrase);
            $this->keepRevision($actor, $page, $data->imageId, $data->ogImageId);

            $event = $creating ? 'page.created' : 'page.updated';

            activity('admin')
                ->causedBy($actor)
                ->performedOn($page)
                ->event($event)
                ->withProperties($creating ? [] : ['fields' => array_values(array_diff($changedFields, ['created_by', 'updated_by']))])
                ->log($event);

            return $page;
        });
    }

    /**
     * Keeps the saved version unless it equals the newest revision (e.g. only the status changed),
     * and removes revisions beyond the last Page::REVISIONS_KEPT.
     */
    private function keepRevision(User $actor, Page $page, ?int $imageId, ?int $ogImageId): void
    {
        $snapshot = [
            ...$page->only(array_diff(PageRevision::FIELDS, ['image_id', 'og_image_id'])),
            'image_id' => $imageId,
            'og_image_id' => $ogImageId,
        ];
        $latest = $page->revisions()->first();

        if ($latest !== null && $this->sameSnapshot($latest->snapshot(), $snapshot)) {
            return;
        }

        $page->revisions()->create([...$snapshot, 'saved_by' => $actor->id]);

        $kept = $page->revisions()->limit(Page::REVISIONS_KEPT)->pluck('id');
        PageRevision::query()->where('page_id', $page->id)->whereNotIn('id', $kept)->delete();
    }

    /**
     * @param  array<string, mixed>  $first
     * @param  array<string, mixed>  $second
     */
    private function sameSnapshot(array $first, array $second): bool
    {
        foreach (PageRevision::FIELDS as $field) {
            if (($first[$field] ?? null) !== ($second[$field] ?? null)) {
                return false;
            }
        }

        return true;
    }
}

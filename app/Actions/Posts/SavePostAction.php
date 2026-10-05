<?php

declare(strict_types=1);

namespace App\Actions\Posts;

use App\Actions\Products\ResolveTagsAction;
use App\Actions\Seo\ScoreSeoAction;
use App\Data\Posts\PostData;
use App\Enums\MediaUsageRole;
use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\PostRevision;
use App\Models\User;
use App\Support\RichText;
use Illuminate\Support\Facades\DB;

final readonly class SavePostAction
{
    public function __construct(
        private ScoreSeoAction $scoreSeo,
        private ResolveTagsAction $resolveTags,
    ) {}

    /**
     * Create ($post null) or update a post with its categories and tags, then keep a revision of
     * what was saved (like pages).
     */
    public function handle(User $actor, ?Post $post, PostData $data): Post
    {
        return DB::transaction(function () use ($actor, $post, $data): Post {
            $creating = $post === null;
            $post ??= new Post;
            $previousKeyphrase = $post->focus_keyphrase;

            $post->fill([
                'title' => $data->title,
                'slug' => $data->slug,
                'excerpt' => $data->excerpt,
                'content' => $data->content,
                'status' => $data->status,
                'published_at' => $data->publishedAt ?? ($data->status === PostStatus::Published ? ($post->published_at ?? now()) : null),
                // New posts are by the one writing them unless another staff member is chosen.
                'author_id' => $data->authorId ?? ($creating ? $actor->id : $post->author_id),
                'comments_enabled' => $data->commentsEnabled,
                'meta_title' => $data->metaTitle,
                'meta_description' => $data->metaDescription,
                'focus_keyphrase' => $data->focusKeyphrase,
                'robots_noindex' => $data->robotsNoindex,
                'canonical_url' => $data->canonicalUrl,
                'og_title' => $data->ogTitle,
                'og_description' => $data->ogDescription,
            ]);

            $changedFields = array_keys($post->getDirty());

            if ($creating) {
                $post->created_by = $actor->id;
                $post->save();
            }

            $categories = [];

            foreach ($data->categoryIds as $categoryId) {
                $categories[$categoryId] = ['is_primary' => $categoryId === $data->primaryCategoryId];
            }

            if (array_filter($post->categories()->sync($categories)) !== []) {
                $changedFields[] = 'categories';
            }

            if (array_filter($post->tags()->sync($this->resolveTags->handle($data->tags))) !== []) {
                $changedFields[] = 'tags';
            }

            if ($post->syncMediaAssets(MediaUsageRole::Image, $data->imageId === null ? [] : [$data->imageId])) {
                $changedFields[] = 'image';
            }

            if ($post->syncMediaAssets(MediaUsageRole::OgImage, $data->ogImageId === null ? [] : [$data->ogImageId])) {
                $changedFields[] = 'og_image';
            }

            // Library files shown in the content count as used, so they can't be deleted meanwhile.
            $post->syncMediaAssets(MediaUsageRole::Content, RichText::mediaIds($post->content));

            // What the saver had autosaved is now either saved or deliberately left out.
            $post->autosaves()->where('user_id', $actor->id)->delete();

            if (! $creating && $changedFields === []) {
                return $post;
            }

            $post->updated_by = $actor->id;
            $post->save();

            $this->scoreSeo->handle($post, $previousKeyphrase);
            $this->keepRevision($actor, $post, $data->imageId, $data->ogImageId);

            $event = $creating ? 'post.created' : 'post.updated';

            activity('admin')
                ->causedBy($actor)
                ->performedOn($post)
                ->event($event)
                ->withProperties($creating ? [] : ['fields' => array_values(array_diff($changedFields, ['created_by', 'updated_by']))])
                ->log($event);

            return $post;
        });
    }

    /**
     * Keeps the saved version unless it equals the newest revision (e.g. only the status, the
     * categories or the tags changed), and removes revisions beyond the last Post::REVISIONS_KEPT.
     */
    private function keepRevision(User $actor, Post $post, ?int $imageId, ?int $ogImageId): void
    {
        $snapshot = [
            ...$post->only(array_diff(PostRevision::FIELDS, ['image_id', 'og_image_id'])),
            'image_id' => $imageId,
            'og_image_id' => $ogImageId,
        ];
        $latest = $post->revisions()->first();

        if ($latest !== null && $this->sameSnapshot($latest->snapshot(), $snapshot)) {
            return;
        }

        $post->revisions()->create([...$snapshot, 'saved_by' => $actor->id]);

        $kept = $post->revisions()->limit(Post::REVISIONS_KEPT)->pluck('id');
        PostRevision::query()->where('post_id', $post->id)->whereNotIn('id', $kept)->delete();
    }

    /**
     * @param  array<string, mixed>  $first
     * @param  array<string, mixed>  $second
     */
    private function sameSnapshot(array $first, array $second): bool
    {
        foreach (PostRevision::FIELDS as $field) {
            if (($first[$field] ?? null) !== ($second[$field] ?? null)) {
                return false;
            }
        }

        return true;
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Posts;

use App\Data\Posts\PostAutosaveData;
use App\Models\Post;
use App\Models\PostAutosave;
use App\Models\User;

final class SavePostAutosaveAction
{
    /**
     * Keep the staff member's unsaved form of the post (one per post and person, overwritten each
     * time). The post, its versions, the audit log and media usages are left alone.
     */
    public function handle(User $actor, Post $post, PostAutosaveData $data): PostAutosave
    {
        $autosave = PostAutosave::query()->updateOrCreate(['post_id' => $post->id, 'user_id' => $actor->id], [
            'title' => $data->title,
            'slug' => $data->slug,
            'excerpt' => $data->excerpt,
            'content' => $data->content,
            'image_id' => $data->imageId,
            'meta_title' => $data->metaTitle,
            'meta_description' => $data->metaDescription,
            'focus_keyphrase' => $data->focusKeyphrase,
            'robots_noindex' => $data->robotsNoindex,
            'canonical_url' => $data->canonicalUrl,
            'og_title' => $data->ogTitle,
            'og_description' => $data->ogDescription,
            'og_image_id' => $data->ogImageId,
        ]);

        // Unchanged values don't touch updated_at, but the form was still just saved.
        $autosave->touch();

        return $autosave;
    }

    /**
     * Throw away the staff member's autosave ("Discard").
     */
    public function discard(User $actor, Post $post): void
    {
        $post->autosaves()->where('user_id', $actor->id)->delete();
    }
}

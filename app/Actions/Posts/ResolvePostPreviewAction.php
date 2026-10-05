<?php

declare(strict_types=1);

namespace App\Actions\Posts;

use App\Enums\MediaType;
use App\Models\MediaAsset;
use App\Models\Post;
use App\Support\PreviewToken;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class ResolvePostPreviewAction
{
    /**
     * The post a token shows, whatever its status (not when in the trash), with the staff member's
     * autosave applied when it is newer. Nothing is saved.
     *
     * @throws ModelNotFoundException<Post> for invalid or expired tokens
     */
    public function handle(string $token): Post
    {
        $claims = PreviewToken::read($token, PreviewToken::POST) ?? throw (new ModelNotFoundException)->setModel(Post::class);
        $post = Post::query()->with(['imageAssets.media', 'ogImageAssets.media', 'author.avatarAssets.media', 'categories', 'tags'])->findOrFail($claims['id']);
        $autosave = $post->autosaves()->where('user_id', $claims['user_id'])->first();

        if ($autosave === null || ! $autosave->isNewerThan($post)) {
            return $post;
        }

        $post->forceFill(array_filter(
            $autosave->only(['title', 'slug', 'excerpt', 'content', 'meta_title', 'meta_description', 'robots_noindex', 'canonical_url', 'og_title', 'og_description']),
            fn (mixed $value, string $key): bool => $value !== null || ! in_array($key, ['title', 'slug'], true),
            ARRAY_FILTER_USE_BOTH,
        ));

        $images = MediaAsset::query()
            ->whereKey(array_filter([$autosave->image_id, $autosave->og_image_id]))
            ->where('type', MediaType::Image)
            ->with('media')
            ->get();
        $post->setRelation('imageAssets', $images->where('id', $autosave->image_id)->values());
        $post->setRelation('ogImageAssets', $images->where('id', $autosave->og_image_id)->values());

        return $post;
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A staff member's unsaved form of a post (the editor sends it every 30 seconds). It never changes
 * the post: the editor offers to restore it, and previews show it to the staff member who made it.
 */
#[Fillable([
    'post_id', 'user_id', 'title', 'slug', 'excerpt', 'content', 'image_id', 'meta_title', 'meta_description',
    'focus_keyphrase', 'robots_noindex', 'canonical_url', 'og_title', 'og_description', 'og_image_id',
])]
class PostAutosave extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'post_id' => 'integer',
            'user_id' => 'integer',
            'image_id' => 'integer',
            'og_image_id' => 'integer',
            'robots_noindex' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function image(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'image_id');
    }

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function ogImage(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'og_image_id');
    }

    /**
     * Whether it holds changes made after the post was last saved.
     */
    public function isNewerThan(Post $post): bool
    {
        return $post->updated_at === null || ($this->updated_at !== null && $this->updated_at->greaterThan($post->updated_at));
    }
}

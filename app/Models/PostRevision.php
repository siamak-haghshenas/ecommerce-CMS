<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A saved version of a post: what the post looked like after one save. Restoring one happens in
 * the editor (it is loaded as unsaved changes), so it never changes the post by itself.
 */
#[Fillable([
    'post_id', 'title', 'slug', 'excerpt', 'content', 'image_id', 'meta_title', 'meta_description', 'focus_keyphrase',
    'robots_noindex', 'canonical_url', 'og_title', 'og_description', 'og_image_id', 'saved_by',
])]
class PostRevision extends Model
{
    /** Revisions are never edited. */
    public const UPDATED_AT = null;

    /** The fields a revision (and an autosave) keeps: the post's content columns plus its two images (not its categories or tags). */
    public const array FIELDS = [
        'title', 'slug', 'excerpt', 'content', 'image_id', 'meta_title', 'meta_description', 'focus_keyphrase',
        'robots_noindex', 'canonical_url', 'og_title', 'og_description', 'og_image_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'post_id' => 'integer',
            'image_id' => 'integer',
            'og_image_id' => 'integer',
            'robots_noindex' => 'boolean',
            'saved_by' => 'integer',
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
     * @return BelongsTo<User, $this>
     */
    public function saver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'saved_by');
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
     * The saved fields, to compare with the post's current ones.
     *
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        return $this->only(self::FIELDS);
    }
}

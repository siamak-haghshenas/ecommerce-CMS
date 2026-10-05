<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A saved version of a page: what the page looked like after one save. Restoring one happens in
 * the editor (it is loaded as unsaved changes), so it never changes the page by itself.
 */
#[Fillable([
    'page_id', 'title', 'slug', 'excerpt', 'content', 'image_id', 'meta_title', 'meta_description', 'focus_keyphrase',
    'robots_noindex', 'canonical_url', 'og_title', 'og_description', 'og_image_id', 'saved_by',
])]
class PageRevision extends Model
{
    /** Revisions are never edited. */
    public const UPDATED_AT = null;

    /** The fields a revision (and an autosave) keeps: the page's content columns plus its two images. */
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
            'page_id' => 'integer',
            'image_id' => 'integer',
            'og_image_id' => 'integer',
            'robots_noindex' => 'boolean',
            'saved_by' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Page, $this>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
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
     * The saved fields, to compare with the page's current ones.
     *
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        return $this->only(self::FIELDS);
    }
}

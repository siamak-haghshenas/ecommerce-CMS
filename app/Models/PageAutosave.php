<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A staff member's unsaved form of a page (the editor sends it every 30 seconds). It never changes
 * the page: the editor offers to restore it, and previews show it to the staff member who made it.
 */
#[Fillable([
    'page_id', 'user_id', 'title', 'slug', 'excerpt', 'content', 'image_id', 'meta_title', 'meta_description',
    'focus_keyphrase', 'robots_noindex', 'canonical_url', 'og_title', 'og_description', 'og_image_id',
])]
class PageAutosave extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'page_id' => 'integer',
            'user_id' => 'integer',
            'image_id' => 'integer',
            'og_image_id' => 'integer',
            'robots_noindex' => 'boolean',
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
     * Whether it holds changes made after the page was last saved.
     */
    public function isNewerThan(Page $page): bool
    {
        return $page->updated_at === null || ($this->updated_at !== null && $this->updated_at->greaterThan($page->updated_at));
    }
}

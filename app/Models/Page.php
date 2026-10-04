<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\SeoSubject;
use App\Data\Pages\PageFilters;
use App\Enums\MediaUsageRole;
use App\Enums\PageStatus;
use App\Models\Concerns\HasSeo;
use App\Models\Concerns\UsesMediaLibrary;
use App\Support\RichText;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A standalone content page (About us, Terms, ...). Deleted pages go to the trash and are
 * removed for good after TRASH_DAYS; every save keeps a revision (the last REVISIONS_KEPT).
 */
#[Fillable([
    'title', 'slug', 'excerpt', 'content', 'status', 'published_at', 'meta_title', 'meta_description',
    'focus_keyphrase', 'created_by', 'updated_by',
])]
class Page extends Model implements SeoSubject
{
    /** @use HasFactory<PageFactory> */
    use HasFactory, HasSeo, Prunable, SoftDeletes, UsesMediaLibrary;

    public const int TRASH_DAYS = 30;

    public const int REVISIONS_KEPT = 20;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PageStatus::class,
            'published_at' => 'datetime',
            'seo_score' => 'integer',
            'readability_score' => 'integer',
            'created_by' => 'integer',
            'updated_by' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Newest first.
     *
     * @return HasMany<PageRevision, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(PageRevision::class)->latest('id');
    }

    /**
     * The featured image (at most one).
     *
     * @return MorphToMany<MediaAsset, $this>
     */
    public function imageAssets(): MorphToMany
    {
        return $this->mediaAssetsFor(MediaUsageRole::Image);
    }

    public function imageAsset(): ?MediaAsset
    {
        return $this->imageAssets->first();
    }

    /**
     * "draft", "scheduled" (published with a future date) or "published".
     */
    public function state(): string
    {
        if ($this->status === PageStatus::Draft) {
            return 'draft';
        }

        return $this->published_at !== null && $this->published_at->isFuture() ? 'scheduled' : 'published';
    }

    /**
     * Pages storefronts may show: published, publish date passed, not in the trash.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function visible(Builder $query): void
    {
        $query->where('status', PageStatus::Published)
            ->where(fn (Builder $date) => $date->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    /**
     * The admin list's filters.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function matching(Builder $query, PageFilters $filters): void
    {
        $query->when($filters->trashed, fn (Builder $query) => $query->onlyTrashed())
            ->when($filters->search !== null, function (Builder $query) use ($filters): void {
                $search = '%'.trim((string) $filters->search).'%';
                $query->where(fn (Builder $match) => $match->where('title', 'like', $search)->orWhere('slug', 'like', $search));
            })
            ->when($filters->state === 'draft', fn (Builder $query) => $query->where('status', PageStatus::Draft))
            ->when($filters->state === 'published', fn (Builder $query) => $query->visible())
            ->when($filters->state === 'scheduled', fn (Builder $query) => $query->where('status', PageStatus::Published)->where('published_at', '>', now()))
            ->when($filters->needsSeo, fn (Builder $query) => $query->where(fn (Builder $seo) => $seo->whereNull('seo_score')->orWhere('seo_score', '<', 70)));
    }

    /**
     * Pages in the trash longer than TRASH_DAYS are removed for good (with their revisions),
     * which frees their library files.
     *
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return self::onlyTrashed()->where('deleted_at', '<=', now()->subDays(self::TRASH_DAYS));
    }

    public function seoName(): string
    {
        return (string) $this->title;
    }

    public function seoDescription(): string
    {
        return RichText::toPlainText((string) $this->content);
    }

    public function hasSeoImage(): bool
    {
        return $this->imageAssets()->exists();
    }
}

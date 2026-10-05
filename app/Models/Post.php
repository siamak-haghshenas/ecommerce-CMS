<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\SeoSubject;
use App\Data\Posts\PostFilters;
use App\Enums\MediaUsageRole;
use App\Enums\PostStatus;
use App\Models\Concerns\HasSeo;
use App\Models\Concerns\UsesMediaLibrary;
use App\Support\CategoryTree;
use App\Support\RichText;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A blog post. Works like a page (trash, revisions, autosave, preview, SEO and social fields) plus
 * an author, blog categories (one primary) and the tags products use.
 */
#[Fillable([
    'title', 'slug', 'excerpt', 'content', 'status', 'published_at', 'author_id', 'comments_enabled', 'meta_title',
    'meta_description', 'focus_keyphrase', 'robots_noindex', 'canonical_url', 'og_title', 'og_description',
    'created_by', 'updated_by',
])]
class Post extends Model implements SeoSubject
{
    /** @use HasFactory<PostFactory> */
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
            'status' => PostStatus::class,
            'published_at' => 'datetime',
            'author_id' => 'integer',
            'comments_enabled' => 'boolean',
            'robots_noindex' => 'boolean',
            'seo_score' => 'integer',
            'readability_score' => 'integer',
            'created_by' => 'integer',
            'updated_by' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
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
     * Blog categories; one is primary (breadcrumbs).
     *
     * @return BelongsToMany<Category, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)->withPivot('is_primary');
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->orderBy('name');
    }

    /**
     * Newest first.
     *
     * @return HasMany<PostRevision, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(PostRevision::class)->latest('id');
    }

    /**
     * Unsaved forms of the post, one per staff member.
     *
     * @return HasMany<PostAutosave, $this>
     */
    public function autosaves(): HasMany
    {
        return $this->hasMany(PostAutosave::class);
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
     * The Open Graph image for social media previews (at most one).
     *
     * @return MorphToMany<MediaAsset, $this>
     */
    public function ogImageAssets(): MorphToMany
    {
        return $this->mediaAssetsFor(MediaUsageRole::OgImage);
    }

    public function ogImageAsset(): ?MediaAsset
    {
        return $this->ogImageAssets->first();
    }

    public function primaryCategory(): ?Category
    {
        return $this->categories->first(fn (Category $category): bool => (bool) $category->getRelationValue('pivot')?->getAttribute('is_primary'))
            ?? $this->categories->first();
    }

    /**
     * "draft", "scheduled" (published with a future date) or "published".
     */
    public function state(): string
    {
        if ($this->status === PostStatus::Draft) {
            return 'draft';
        }

        return $this->published_at !== null && $this->published_at->isFuture() ? 'scheduled' : 'published';
    }

    /**
     * Posts storefronts may show: published, publish date passed, not in the trash. Categories are
     * optional, so a post without a visible category is still shown.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function visible(Builder $query): void
    {
        $query->where('status', PostStatus::Published)
            ->where(fn (Builder $date) => $date->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    /**
     * The admin list's filters.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function matching(Builder $query, PostFilters $filters): void
    {
        $query->when($filters->trashed, fn (Builder $query) => $query->onlyTrashed())
            ->when($filters->search !== null, function (Builder $query) use ($filters): void {
                $search = '%'.trim((string) $filters->search).'%';
                $query->where(fn (Builder $match) => $match->where('title', 'like', $search)->orWhere('slug', 'like', $search));
            })
            ->when($filters->state === 'draft', fn (Builder $query) => $query->where('status', PostStatus::Draft))
            ->when($filters->state === 'published', fn (Builder $query) => $query->visible())
            ->when($filters->state === 'scheduled', fn (Builder $query) => $query->where('status', PostStatus::Published)->where('published_at', '>', now()))
            ->when($filters->categoryId, fn (Builder $query, int $id) => $query->whereHas('categories', fn (Builder $categories) => $categories->whereKey(
                [$id, ...CategoryTree::current()->descendantIds($id)],
            )))
            ->when($filters->tagId, fn (Builder $query, int $id) => $query->whereHas('tags', fn (Builder $tags) => $tags->whereKey($id)))
            ->when($filters->authorId, fn (Builder $query, int $id) => $query->where('author_id', $id))
            ->when($filters->needsSeo, fn (Builder $query) => $query->where(fn (Builder $seo) => $seo->whereNull('seo_score')->orWhere('seo_score', '<', 70)));
    }

    /**
     * Posts in the trash longer than TRASH_DAYS are removed for good (with their revisions),
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

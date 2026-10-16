<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\SeoSubject;
use App\Enums\CategoryType;
use App\Enums\MediaUsageRole;
use App\Models\Concerns\HasSeo;
use App\Models\Concerns\IsCouponTarget;
use App\Models\Concerns\UsesMediaLibrary;
use App\Support\CategoryTree;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * A product or blog category (`type`; one table, a tree never mixes the two). The tree has unlimited depth and is stored as `parent_id` only;
 * tree questions (ancestors, descendants, visibility) are answered by CategoryTree. Its image is a
 * file of the media library (role `image`).
 */
#[Fillable(['type', 'parent_id', 'name', 'slug', 'description', 'meta_title', 'meta_description', 'focus_keyphrase', 'position', 'is_active'])]
class Category extends Model implements SeoSubject
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory, HasSeo, IsCouponTarget, UsesMediaLibrary;

    protected static function booted(): void
    {
        // The cached tree must always reflect the database.
        static::saved(fn () => CategoryTree::forget());
        static::deleted(fn () => CategoryTree::forget());
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CategoryType::class,
            'parent_id' => 'integer',
            'position' => 'integer',
            'is_active' => 'boolean',
            'seo_score' => 'integer',
            'readability_score' => 'integer',
        ];
    }

    /**
     * The position that places a category last among the children of the given parent (or among
     * the top-level categories of its type).
     */
    public static function nextPositionUnder(?int $parentId, CategoryType $type): int
    {
        $lastPosition = self::query()->where('type', $type)->where('parent_id', $parentId)->max('position');

        return $lastPosition === null ? 0 : (int) $lastPosition + 1;
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<self, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Posts in this blog category (product categories have none).
     *
     * @return BelongsToMany<Post, $this>
     */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class);
    }

    /**
     * The category's own attributes, in order. Sub-categories inherit them (see CategoryAttributeController).
     *
     * @return BelongsToMany<Attribute, $this>
     */
    public function attributeDefinitions(): BelongsToMany
    {
        return $this->belongsToMany(Attribute::class)->withPivot('position')->orderByPivot('position');
    }

    /**
     * The category image (at most one).
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
     * Duplicate keyphrases are only compared among categories of the same type.
     *
     * @return array<string, string>
     */
    public function seoPeerConstraints(): array
    {
        return ['type' => $this->type->value];
    }

    public function hasSeoImage(): bool
    {
        return $this->imageAssets()->exists();
    }
}

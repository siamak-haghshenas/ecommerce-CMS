<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\SeoSubject;
use App\Models\Concerns\HasSeo;
use App\Support\CategoryTree;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A catalog category. The tree has unlimited depth and is stored as `parent_id` only;
 * tree questions (ancestors, descendants, visibility) are answered by CategoryTree.
 */
#[Fillable(['parent_id', 'name', 'slug', 'description', 'meta_title', 'meta_description', 'focus_keyphrase', 'position', 'is_active'])]
class Category extends Model implements HasMedia, SeoSubject
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory, HasSeo, InteractsWithMedia;

    public const string IMAGE_COLLECTION = 'image';

    /** Image conversions, smallest first; the API returns a URL for each plus the original. */
    public const array IMAGE_CONVERSIONS = ['thumb', 'medium'];

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
            'parent_id' => 'integer',
            'position' => 'integer',
            'is_active' => 'boolean',
            'seo_score' => 'integer',
            'readability_score' => 'integer',
        ];
    }

    /**
     * The position that places a category last among the children of the given parent.
     */
    public static function nextPositionUnder(?int $parentId): int
    {
        $lastPosition = self::query()->where('parent_id', $parentId)->max('position');

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
     * The category's own attributes, in order. Sub-categories inherit them (see CategoryAttributeController).
     *
     * @return BelongsToMany<Attribute, $this>
     */
    public function attributeDefinitions(): BelongsToMany
    {
        return $this->belongsToMany(Attribute::class)->withPivot('position')->orderByPivot('position');
    }

    public function seoImageCollection(): string
    {
        return self::IMAGE_COLLECTION;
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::IMAGE_COLLECTION)
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        // Generated during the upload request (not queued) so the URLs are ready immediately.
        $this->addMediaConversion('thumb')
            ->nonQueued()
            ->format('webp')
            ->fit(Fit::Crop, 240, 240);

        $this->addMediaConversion('medium')
            ->nonQueued()
            ->format('webp')
            ->fit(Fit::Max, 960, 960);
    }

    /**
     * URLs of the category image, or null when it has none.
     *
     * @return array{original: string, thumb: string, medium: string}|null
     */
    public function imageUrls(): ?array
    {
        $media = $this->getFirstMedia(self::IMAGE_COLLECTION);

        if ($media === null) {
            return null;
        }

        return [
            'original' => $media->getUrl(),
            'thumb' => $media->getAvailableUrl(['thumb']),
            'medium' => $media->getAvailableUrl(['medium']),
        ];
    }
}

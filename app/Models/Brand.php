<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\SeoSubject;
use App\Models\Concerns\HasSeo;
use Database\Factories\BrandFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A product brand. Brands form a flat list in a manual order (`position`).
 */
#[Fillable(['name', 'persian_name', 'slug', 'description', 'website', 'position', 'is_active', 'meta_title', 'meta_description', 'focus_keyphrase'])]
class Brand extends Model implements HasMedia, SeoSubject
{
    /** @use HasFactory<BrandFactory> */
    use HasFactory, HasSeo, InteractsWithMedia;

    public const string LOGO_COLLECTION = 'logo';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_active' => 'boolean',
            'seo_score' => 'integer',
            'readability_score' => 'integer',
        ];
    }

    /**
     * The position that places a brand last.
     */
    public static function nextPosition(): int
    {
        $lastPosition = self::query()->max('position');

        return $lastPosition === null ? 0 : (int) $lastPosition + 1;
    }

    /**
     * Brands in their manual order.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('id');
    }

    public function seoImageCollection(): string
    {
        return self::LOGO_COLLECTION;
    }

    public function registerMediaCollections(): void
    {
        // No SVG: it can carry scripts, and conversions can't be made from it.
        $this->addMediaCollection(self::LOGO_COLLECTION)
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        // Logos are fitted, never cropped; webp keeps PNG transparency.
        $this->addMediaConversion('thumb')
            ->nonQueued()
            ->format('webp')
            ->fit(Fit::Contain, 240, 240);

        $this->addMediaConversion('medium')
            ->nonQueued()
            ->format('webp')
            ->fit(Fit::Max, 600, 600);
    }

    /**
     * URLs of the logo, or null when the brand has none.
     *
     * @return array{original: string, thumb: string, medium: string}|null
     */
    public function logoUrls(): ?array
    {
        $media = $this->getFirstMedia(self::LOGO_COLLECTION);

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

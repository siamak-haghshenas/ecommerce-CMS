<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\SeoSubject;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\Concerns\HasSeo;
use App\Support\RichText;
use App\Support\Settings;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A product. Every product sells through variants (SKUs): a simple product has exactly one.
 * Products are soft-deleted, since orders will point at them.
 */
#[Fillable([
    'type', 'name', 'slug', 'short_description', 'description', 'status', 'published_at', 'brand_id',
    'track_stock', 'allow_backorders', 'low_stock_threshold', 'weight_grams', 'length_mm', 'width_mm',
    'height_mm', 'meta_title', 'meta_description', 'focus_keyphrase',
])]
class Product extends Model implements HasMedia, SeoSubject
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, HasSeo, InteractsWithMedia, SoftDeletes;

    public const string GALLERY_COLLECTION = 'gallery';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'status' => ProductStatus::class,
            'published_at' => 'datetime',
            'brand_id' => 'integer',
            'track_stock' => 'boolean',
            'allow_backorders' => 'boolean',
            'low_stock_threshold' => 'integer',
            'weight_grams' => 'integer',
            'length_mm' => 'integer',
            'width_mm' => 'integer',
            'height_mm' => 'integer',
            'seo_score' => 'integer',
            'readability_score' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * @return BelongsToMany<Category, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)->withPivot('is_primary');
    }

    /**
     * The options the product varies by, in order (Size before Color).
     *
     * @return BelongsToMany<Option, $this>
     */
    public function options(): BelongsToMany
    {
        return $this->belongsToMany(Option::class, 'product_options')->withPivot('position')->orderByPivot('position');
    }

    /**
     * @return HasMany<ProductVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('position')->orderBy('id');
    }

    /**
     * @return HasMany<ProductAttributeValue, $this>
     */
    public function attributeValues(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class);
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->orderBy('name');
    }

    public function primaryCategory(): ?Category
    {
        return $this->categories->first(fn (Category $category): bool => (bool) $category->getRelationValue('pivot')?->getAttribute('is_primary'));
    }

    /**
     * The stock at or below which a variant counts as "low": the product's own, else the store default.
     */
    public function lowStockThreshold(): int
    {
        return $this->low_stock_threshold ?? app(Settings::class)->lowStockThreshold();
    }

    /**
     * Can shoppers buy this variant now? Untracked stock and backorders are always available.
     */
    public function isAvailable(ProductVariant $variant): bool
    {
        return $variant->is_enabled && (! $this->track_stock || $this->allow_backorders || $variant->stock_quantity > 0);
    }

    /**
     * Available, but at or below the low-stock threshold ("only a few left").
     */
    public function isLowStock(ProductVariant $variant): bool
    {
        return $this->track_stock && $variant->stock_quantity > 0 && $variant->stock_quantity <= $this->lowStockThreshold();
    }

    public function seoImageCollection(): string
    {
        return self::GALLERY_COLLECTION;
    }

    /**
     * The analysis reads the description as plain text, not HTML.
     */
    public function seoDescription(): string
    {
        return RichText::toPlainText((string) $this->description);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::GALLERY_COLLECTION)
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        // Products are fitted, never cropped, so nothing of the product is cut off.
        $this->addMediaConversion('thumb')->nonQueued()->format('webp')->fit(Fit::Contain, 320, 320);
        $this->addMediaConversion('medium')->nonQueued()->format('webp')->fit(Fit::Max, 800, 800);
        $this->addMediaConversion('large')->nonQueued()->format('webp')->fit(Fit::Max, 1600, 1600);
    }

    /**
     * The gallery in order; the first image is the main image.
     *
     * @return list<array{id: int, original: string, thumb: string, medium: string, large: string}>
     */
    public function images(): array
    {
        return $this->getMedia(self::GALLERY_COLLECTION)
            ->map(fn (Media $media): array => [
                'id' => (int) $media->id,
                'original' => $media->getUrl(),
                'thumb' => $media->getAvailableUrl(['thumb']),
                'medium' => $media->getAvailableUrl(['medium']),
                'large' => $media->getAvailableUrl(['large']),
            ])
            ->values()
            ->all();
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\SeoSubject;
use App\Data\Products\ProductFilters;
use App\Enums\MediaUsageRole;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\Concerns\HasComments;
use App\Models\Concerns\HasSeo;
use App\Models\Concerns\UsesMediaLibrary;
use App\Support\CategoryTree;
use App\Support\RichText;
use App\Support\Seo\SeoText;
use App\Support\Settings;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * A product. Every product sells through variants (SKUs): a simple product has exactly one.
 * Products are soft-deleted, since orders will point at them.
 */
#[Fillable([
    'type', 'name', 'slug', 'short_description', 'description', 'status', 'published_at', 'brand_id',
    'track_stock', 'allow_backorders', 'comments_enabled', 'low_stock_threshold', 'weight_grams', 'length_mm', 'width_mm',
    'height_mm', 'meta_title', 'meta_description', 'focus_keyphrase',
])]
class Product extends Model implements SeoSubject
{
    /** @use HasFactory<ProductFactory> */
    use HasComments, HasFactory, HasSeo, SoftDeletes, UsesMediaLibrary;

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
            'comments_enabled' => 'boolean',
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

    /**
     * The admin list's filters (also the bulk editor's).
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function matching(Builder $query, ProductFilters $filters): void
    {
        $query->when($filters->trashed, fn (Builder $query) => $query->onlyTrashed());

        // Every word must appear, typed with any keyboard ("كفش ۴۲" finds "کفش 42").
        foreach (SeoText::words((string) $filters->search) as $word) {
            $query->where('search_text', 'like', '%'.addcslashes($word, '%_\\').'%');
        }

        $query
            ->when($filters->status, fn (Builder $query, $status) => $query->where('status', $status))
            ->when($filters->brandId, fn (Builder $query, int $id) => $query->where('brand_id', $id))
            ->when($filters->tagId, fn (Builder $query, int $id) => $query->whereHas('tags', fn (Builder $tags) => $tags->whereKey($id)))
            // A category includes its sub-categories.
            ->when($filters->categoryId, fn (Builder $query, int $id) => $query->whereHas('categories', fn (Builder $categories) => $categories->whereKey(
                [$id, ...CategoryTree::current()->descendantIds($id)],
            )))
            ->when($filters->needsSeo, fn (Builder $query) => $query->where(fn (Builder $seo) => $seo->whereNull('seo_score')->orWhere('seo_score', '<', 70)));

        $stock = in_array($filters->quick, ['low', 'out'], true) ? $filters->quick : $filters->stock;

        if ($stock !== null) {
            $this->filterStock($query, $stock);
        }

        if ($filters->quick === 'sale') {
            // A running sale on an enabled variant.
            $query->whereHas('variants', fn (Builder $variants) => $variants
                ->where('is_enabled', true)
                ->whereNotNull('sale_price')
                ->where(fn (Builder $starts) => $starts->whereNull('sale_starts_at')->orWhere('sale_starts_at', '<=', now()))
                ->where(fn (Builder $ends) => $ends->whereNull('sale_ends_at')->orWhere('sale_ends_at', '>', now())));
        }

        if ($filters->quick === 'inactive') {
            $query->whereHas('variants', fn (Builder $variants) => $variants->where('is_enabled', false));
        }
    }

    /**
     * @param  Builder<self>  $query
     */
    private function filterStock(Builder $query, string $stock): void
    {
        if ($stock === 'in') {
            // An enabled variant shoppers can buy: untracked stock, backorders or a quantity left.
            $query->whereHas('variants', fn (Builder $variants) => $variants->where('is_enabled', true))
                ->where(fn (Builder $available) => $available
                    ->where('track_stock', false)
                    ->orWhere('allow_backorders', true)
                    ->orWhereHas('variants', fn (Builder $variants) => $variants->where('is_enabled', true)->where('stock_quantity', '>', 0)));

            return;
        }

        $query->where('track_stock', true);

        if ($stock === 'low') {
            // An enabled variant at or below the product's threshold (or the store default).
            $query->whereHas('variants', fn (Builder $variants) => $variants
                ->where('is_enabled', true)
                ->where('stock_quantity', '<=', DB::raw('COALESCE(products.low_stock_threshold, '.app(Settings::class)->lowStockThreshold().')')));

            return;
        }

        // Out: nothing left to sell and no backorders.
        $query->where('allow_backorders', false)
            ->whereDoesntHave('variants', fn (Builder $variants) => $variants->where('is_enabled', true)->where('stock_quantity', '>', 0));
    }

    public function hasSeoImage(): bool
    {
        return $this->galleryAssets()->exists();
    }

    /**
     * The analysis reads the description as plain text, not HTML.
     */
    public function seoDescription(): string
    {
        return RichText::toPlainText((string) $this->description);
    }

    /**
     * The gallery: library files in order; the first is the main image. Variant images are chosen
     * from it.
     *
     * @return MorphToMany<MediaAsset, $this>
     */
    public function galleryAssets(): MorphToMany
    {
        return $this->mediaAssetsFor(MediaUsageRole::Gallery);
    }

    /**
     * The gallery's pictures in order (each: the file id, URLs and alt text).
     *
     * @return list<array{id: int, original: string, thumb: string|null, medium: string|null, large: string|null, alt: string|null}>
     */
    public function images(): array
    {
        return $this->galleryAssets
            ->map(fn (MediaAsset $asset): ?array => $asset->picture())
            ->filter()
            ->values()
            ->all();
    }
}

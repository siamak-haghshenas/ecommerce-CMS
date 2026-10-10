<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\SeoSubject;
use App\Enums\MediaUsageRole;
use App\Models\Concerns\HasSeo;
use App\Models\Concerns\UsesMediaLibrary;
use App\Support\RichText;
use Carbon\CarbonInterface;
use Database\Factories\SaleEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * A time-limited sale on chosen variants (weekend sale, end-of-year sale, ...). Each variant has
 * its own discount (SaleEventItem). `position` is the priority: of the events running for a
 * variant, the highest one is used, and its price then competes with the variant's sale price and
 * promotions (the lowest wins; see App\Support\Pricing\Pricing). An event may have a landing page,
 * which storefronts get as data.
 */
#[Fillable([
    'name', 'starts_at', 'ends_at', 'is_active', 'position', 'has_landing_page', 'slug', 'landing_title',
    'landing_description', 'meta_title', 'meta_description', 'focus_keyphrase', 'robots_noindex', 'canonical_url',
    'og_title', 'og_description', 'created_by', 'updated_by',
])]
class SaleEvent extends Model implements SeoSubject
{
    /** @use HasFactory<SaleEventFactory> */
    use HasFactory, HasSeo, UsesMediaLibrary;

    public const int MAX_PERCENT = 90;

    /** At most this many variants per event. */
    public const int MAX_ITEMS = 2000;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
            'position' => 'integer',
            'has_landing_page' => 'boolean',
            'seo_score' => 'integer',
            'readability_score' => 'integer',
            'robots_noindex' => 'boolean',
            'created_by' => 'integer',
            'updated_by' => 'integer',
        ];
    }

    /**
     * @return HasMany<SaleEventItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SaleEventItem::class);
    }

    /**
     * The landing page's banner (at most one).
     *
     * @return MorphToMany<MediaAsset, $this>
     */
    public function bannerAssets(): MorphToMany
    {
        return $this->mediaAssetsFor(MediaUsageRole::Banner);
    }

    public function bannerAsset(): ?MediaAsset
    {
        return $this->bannerAssets->first();
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

    /**
     * "disabled", "scheduled", "active" or "expired" (the same states as promotions).
     */
    public function state(?CarbonInterface $at = null): string
    {
        $at ??= now();

        return match (true) {
            ! $this->is_active => 'disabled',
            $this->starts_at->gt($at) => 'scheduled',
            $this->ends_at->lte($at) => 'expired',
            default => 'active',
        };
    }

    /**
     * The landing page's stage: "upcoming" (counting down to the start), "running" or "ended".
     */
    public function landingStatus(?CarbonInterface $at = null): string
    {
        $at ??= now();

        return match (true) {
            $this->starts_at->gt($at) => 'upcoming',
            $this->ends_at->lte($at) => 'ended',
            default => 'running',
        };
    }

    /**
     * Events prices depend on: enabled and not ended.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function relevant(Builder $query): void
    {
        $query->where('is_active', true)->where('ends_at', '>', now());
    }

    /**
     * Events whose landing page storefronts may show (before the start and after the end too).
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function withVisibleLanding(Builder $query): void
    {
        $query->where('is_active', true)->where('has_landing_page', true)->whereNotNull('slug');
    }

    /**
     * The admin list's status filter.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function inState(Builder $query, string $state): void
    {
        $now = now();

        match ($state) {
            'disabled' => $query->where('is_active', false),
            'scheduled' => $query->where('is_active', true)->where('starts_at', '>', $now),
            'expired' => $query->where('is_active', true)->where('ends_at', '<=', $now),
            default => $query->where('is_active', true)->where('starts_at', '<=', $now)->where('ends_at', '>', $now),
        };
    }

    /**
     * Whether storefronts may show the landing page.
     */
    public function hasVisibleLanding(): bool
    {
        return $this->is_active && $this->has_landing_page && $this->slug !== null;
    }

    public function seoName(): string
    {
        return (string) ($this->landing_title ?? $this->name);
    }

    public function seoDescription(): string
    {
        return RichText::toPlainText((string) $this->landing_description);
    }

    public function hasSeoImage(): bool
    {
        return $this->bannerAssets()->exists();
    }
}

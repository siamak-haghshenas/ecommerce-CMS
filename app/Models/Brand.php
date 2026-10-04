<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\SeoSubject;
use App\Enums\MediaUsageRole;
use App\Models\Concerns\HasSeo;
use App\Models\Concerns\UsesMediaLibrary;
use Database\Factories\BrandFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * A product brand. Brands form a flat list in a manual order (`position`).
 */
#[Fillable(['name', 'persian_name', 'slug', 'description', 'website', 'position', 'is_active', 'meta_title', 'meta_description', 'focus_keyphrase'])]
class Brand extends Model implements SeoSubject
{
    /** @use HasFactory<BrandFactory> */
    use HasFactory, HasSeo, UsesMediaLibrary;

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

    /**
     * The brand logo (at most one).
     *
     * @return MorphToMany<MediaAsset, $this>
     */
    public function logoAssets(): MorphToMany
    {
        return $this->mediaAssetsFor(MediaUsageRole::Logo);
    }

    public function logoAsset(): ?MediaAsset
    {
        return $this->logoAssets->first();
    }

    public function hasSeoImage(): bool
    {
        return $this->logoAssets()->exists();
    }
}

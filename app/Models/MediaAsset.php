<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MediaType;
use Database\Factories\MediaAssetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A file of the media library, stored once and used by any number of items (media_usages).
 * The file itself is the single spatie media of the `file` collection; images get webp versions.
 * Deleting moves it to the trash (soft delete); the trash is emptied after config media.trash_days.
 */
#[Fillable(['folder_id', 'type', 'name', 'mime_type', 'size', 'width', 'height', 'title', 'alt', 'cover_id', 'uploaded_by'])]
class MediaAsset extends Model implements HasMedia
{
    /** @use HasFactory<MediaAssetFactory> */
    use HasFactory, InteractsWithMedia, Prunable, SoftDeletes;

    public const string FILE_COLLECTION = 'file';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'folder_id' => 'integer',
            'type' => MediaType::class,
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'cover_id' => 'integer',
            'uploaded_by' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<MediaFolder, $this>
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(MediaFolder::class, 'folder_id');
    }

    /**
     * A video's cover picture.
     *
     * @return BelongsTo<self, $this>
     */
    public function cover(): BelongsTo
    {
        return $this->belongsTo(self::class, 'cover_id');
    }

    /**
     * Videos using this image as their cover.
     *
     * @return HasMany<self, $this>
     */
    public function coveredVideos(): HasMany
    {
        return $this->hasMany(self::class, 'cover_id');
    }

    /**
     * @return HasMany<MediaUsage, $this>
     */
    public function usages(): HasMany
    {
        return $this->hasMany(MediaUsage::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Used by an item or as a (not deleted) video's cover: such a file can't be deleted.
     */
    public function isInUse(): bool
    {
        return $this->usages()->exists() || $this->coveredVideos()->exists();
    }

    /**
     * Files used somewhere (true) or nowhere (false).
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function used(Builder $query, bool $used = true): void
    {
        $inUse = fn (Builder $query) => $query
            ->whereHas('usages')
            ->orWhereHas('coveredVideos');

        $used ? $query->where($inUse) : $query->whereNot($inUse);
    }

    /**
     * Files in the trash longer than config media.trash_days (and not in use) are removed for good.
     *
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return self::onlyTrashed()
            ->where('deleted_at', '<=', now()->subDays((int) config('media.trash_days')))
            ->used(false);
    }

    public function file(): ?Media
    {
        return $this->getFirstMedia(self::FILE_COLLECTION);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::FILE_COLLECTION)->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        // Only images have versions; documents and videos are kept as uploaded.
        if ($media !== null && ! str_starts_with((string) $media->mime_type, 'image/')) {
            return;
        }

        $this->addMediaConversion('thumb')->nonQueued()->format('webp')->fit(Fit::Contain, 320, 320);
        $this->addMediaConversion('medium')->nonQueued()->format('webp')->fit(Fit::Max, 800, 800);
        $this->addMediaConversion('large')->nonQueued()->format('webp')->fit(Fit::Max, 1600, 1600);
    }

    /**
     * How items (product, category, brand) return a picture: the file's id, URLs and alt text.
     *
     * @return array{id: int, original: string, thumb: string|null, medium: string|null, large: string|null, alt: string|null}|null
     */
    public function picture(): ?array
    {
        $urls = $this->urls();

        return $urls === null ? null : ['id' => $this->id, ...$urls, 'alt' => $this->alt];
    }

    /**
     * URLs of the file and (for images) its versions.
     *
     * @return array{original: string, thumb: string|null, medium: string|null, large: string|null}|null
     */
    public function urls(): ?array
    {
        $file = $this->file();

        if ($file === null) {
            return null;
        }

        $isImage = $this->type === MediaType::Image;

        return [
            'original' => $file->getUrl(),
            'thumb' => $isImage ? $file->getAvailableUrl(['thumb']) : null,
            'medium' => $isImage ? $file->getAvailableUrl(['medium']) : null,
            'large' => $isImage ? $file->getAvailableUrl(['large']) : null,
        ];
    }
}

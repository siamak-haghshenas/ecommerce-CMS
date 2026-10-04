<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Enums\MediaUsageRole;
use App\Models\MediaAsset;
use App\Models\MediaUsage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * An item that uses files of the media library (product gallery, category image, brand logo).
 * The links live in `media_usages`, which also answers "where is this file used". A hard-deleted
 * item frees its files; a soft-deleted one keeps them (it can be restored).
 *
 * @mixin Model
 */
trait UsesMediaLibrary
{
    public static function bootUsesMediaLibrary(): void
    {
        static::deleted(function (Model $model): void {
            // Soft-deleted items keep their files (they can be restored).
            $softDeleted = method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting();

            if (! $softDeleted) {
                MediaUsage::query()
                    ->where('usable_type', $model->getMorphClass())
                    ->where('usable_id', $model->getKey())
                    ->delete();
            }
        });
    }

    /**
     * The library files this item uses in one role, in order.
     *
     * @return MorphToMany<MediaAsset, $this>
     */
    protected function mediaAssetsFor(MediaUsageRole $role): MorphToMany
    {
        return $this->morphToMany(MediaAsset::class, 'usable', 'media_usages')
            ->withPivot(['role', 'position'])
            ->withTimestamps()
            ->wherePivot('role', $role->value)
            ->orderByPivot('position');
    }

    /**
     * Makes the role use exactly these files, in this order. Returns whether anything changed.
     *
     * @param  list<int>  $assetIds
     */
    public function syncMediaAssets(MediaUsageRole $role, array $assetIds): bool
    {
        $usages = MediaUsage::query()
            ->where('usable_type', $this->getMorphClass())
            ->where('usable_id', $this->getKey())
            ->where('role', $role->value);

        if ((clone $usages)->orderBy('position')->pluck('media_asset_id')->map(fn (mixed $id): int => (int) $id)->all() === $assetIds) {
            return false;
        }

        $usages->delete();

        foreach ($assetIds as $position => $assetId) {
            MediaUsage::query()->create([
                'media_asset_id' => $assetId,
                'usable_type' => $this->getMorphClass(),
                'usable_id' => $this->getKey(),
                'role' => $role,
                'position' => $position,
            ]);
        }

        return true;
    }
}

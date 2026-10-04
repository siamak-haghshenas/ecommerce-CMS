<?php

declare(strict_types=1);

namespace App\Actions\Media;

use App\Data\Media\MediaAssetData;
use App\Models\Category;
use App\Models\MediaAsset;
use App\Models\User;
use App\Support\CategoryTree;

final class UpdateMediaAssetAction
{
    /**
     * Saves a file's title, alt text and (for videos) cover picture.
     */
    public function handle(User $actor, MediaAsset $asset, MediaAssetData $data): MediaAsset
    {
        $asset->fill(['title' => $data->title, 'alt' => $data->alt, 'cover_id' => $data->coverId]);
        $changed = array_keys($asset->getDirty());

        if ($changed === []) {
            return $asset;
        }

        $asset->save();

        // The cached category tree carries category images (with their alt text).
        if ($asset->usages()->where('usable_type', (new Category)->getMorphClass())->exists()) {
            CategoryTree::forget();
        }

        activity('admin')
            ->causedBy($actor)
            ->performedOn($asset)
            ->event('media.updated')
            ->withProperties(['name' => $asset->name, 'fields' => $changed])
            ->log('media.updated');

        return $asset;
    }
}

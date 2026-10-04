<?php

declare(strict_types=1);

namespace App\Actions\Media;

use App\Models\MediaAsset;
use App\Models\MediaFolder;
use App\Models\User;

final class MoveMediaAssetsAction
{
    /**
     * Moves files into a folder (null: no folder). Returns how many actually moved.
     *
     * @param  list<int>  $assetIds
     */
    public function handle(User $actor, array $assetIds, ?MediaFolder $folder): int
    {
        $moved = 0;

        foreach (MediaAsset::query()->whereKey($assetIds)->with('folder')->get() as $asset) {
            if ($asset->folder_id === $folder?->id) {
                continue;
            }

            $from = $asset->folder?->name;
            $asset->update(['folder_id' => $folder?->id]);
            $moved++;

            activity('admin')
                ->causedBy($actor)
                ->performedOn($asset)
                ->event('media.moved')
                ->withProperties(['name' => $asset->name, 'from' => $from, 'to' => $folder?->name])
                ->log('media.moved');
        }

        return $moved;
    }
}

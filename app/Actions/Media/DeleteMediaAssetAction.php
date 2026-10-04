<?php

declare(strict_types=1);

namespace App\Actions\Media;

use App\Exceptions\MediaException;
use App\Models\MediaAsset;
use App\Models\User;

/**
 * Moves a file to the trash (restorable until config media.trash_days have passed), and back.
 */
final class DeleteMediaAssetAction
{
    /**
     * @throws MediaException when the file is in use
     */
    public function handle(User $actor, MediaAsset $asset): void
    {
        if ($asset->isInUse()) {
            throw MediaException::inUse($asset->usages()->count() + $asset->coveredVideos()->count());
        }

        $asset->delete();
        $this->log($actor, $asset, 'media.deleted');
    }

    public function restore(User $actor, MediaAsset $asset): void
    {
        if (! $asset->trashed()) {
            return;
        }

        $asset->restore();
        $this->log($actor, $asset, 'media.restored');
    }

    private function log(User $actor, MediaAsset $asset, string $event): void
    {
        activity('admin')
            ->causedBy($actor)
            ->performedOn($asset)
            ->event($event)
            ->withProperties(['name' => $asset->name])
            ->log($event);
    }
}

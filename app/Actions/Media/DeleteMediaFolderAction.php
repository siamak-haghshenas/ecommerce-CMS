<?php

declare(strict_types=1);

namespace App\Actions\Media;

use App\Models\MediaFolder;
use App\Models\User;

final class DeleteMediaFolderAction
{
    /**
     * Deletes a folder; its files are kept and move to "no folder" (by the foreign key).
     */
    public function handle(User $actor, MediaFolder $folder): void
    {
        $files = $folder->assets()->withTrashed()->count();
        $folder->delete();

        activity('admin')
            ->causedBy($actor)
            ->event('media_folder.deleted')
            ->withProperties(['name' => $folder->name, 'files' => $files])
            ->log('media_folder.deleted');
    }
}

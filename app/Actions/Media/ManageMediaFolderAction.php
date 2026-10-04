<?php

declare(strict_types=1);

namespace App\Actions\Media;

use App\Models\MediaFolder;
use App\Models\User;

final class ManageMediaFolderAction
{
    /**
     * Creates a folder, or renames it when one is given.
     */
    public function handle(User $actor, string $name, ?MediaFolder $folder = null): MediaFolder
    {
        $event = $folder === null ? 'media_folder.created' : 'media_folder.renamed';
        $from = $folder?->name;
        $folder ??= new MediaFolder;
        $folder->fill(['name' => $name]);

        if (! $folder->isDirty()) {
            return $folder;
        }

        $folder->save();

        activity('admin')
            ->causedBy($actor)
            ->performedOn($folder)
            ->event($event)
            ->withProperties(array_filter(['name' => $name, 'from' => $from]))
            ->log($event);

        return $folder;
    }
}

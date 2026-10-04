<?php

declare(strict_types=1);

namespace App\Actions\Media;

use App\Data\Media\MediaUploadData;
use App\Models\MediaUpload;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * Opens a chunked upload: an empty temporary file the chunks are appended to.
 */
final class StartMediaUploadAction
{
    public function handle(User $actor, MediaUploadData $data): MediaUpload
    {
        $upload = MediaUpload::query()->create([
            'user_id' => $actor->id,
            'name' => $data->name,
            'mime_type' => $data->mimeType,
            'size' => $data->size,
            'received' => 0,
            'folder_id' => $data->folderId,
            'expires_at' => now()->addHours((int) config('media.upload_expiry_hours')),
        ]);

        Storage::disk('local')->put($upload->path(), '');

        return $upload;
    }
}

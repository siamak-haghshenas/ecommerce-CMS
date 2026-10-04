<?php

declare(strict_types=1);

namespace App\Actions\Media;

use App\Models\MediaUpload;
use Illuminate\Support\Facades\Storage;

final class CancelMediaUploadAction
{
    /**
     * Drops an unfinished upload and its temporary file.
     */
    public function handle(MediaUpload $upload): void
    {
        Storage::disk('local')->delete($upload->path());
        $upload->delete();
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Media;

use App\Exceptions\MediaException;
use App\Models\MediaUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Appends one chunk to an upload. A chunk must start where the upload stopped (`offset` =
 * `received`), so a retried or out-of-order chunk can't corrupt the file.
 */
final class AppendMediaChunkAction
{
    /**
     * @throws MediaException when the chunk doesn't continue the file or goes past its size
     */
    public function handle(MediaUpload $upload, UploadedFile $chunk, int $offset): MediaUpload
    {
        return DB::transaction(function () use ($upload, $chunk, $offset): MediaUpload {
            $locked = MediaUpload::query()->whereKey($upload->id)->lockForUpdate()->firstOrFail();

            if ($offset !== $locked->received) {
                throw MediaException::chunkOutOfOrder($locked->received);
            }

            $length = (int) $chunk->getSize();

            if ($locked->received + $length > $locked->size) {
                throw MediaException::chunkTooLarge();
            }

            $target = fopen(Storage::disk('local')->path($locked->path()), 'ab');
            $source = fopen($chunk->getRealPath(), 'rb');
            stream_copy_to_stream($source, $target);
            fclose($source);
            fclose($target);

            $locked->update(['received' => $locked->received + $length]);

            return $locked;
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Media;

use App\Enums\MediaType;
use App\Exceptions\MediaException;
use App\Models\MediaAsset;
use App\Models\MediaUpload;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;
use Symfony\Component\Mime\MimeTypes;
use Throwable;

/**
 * Turns a finished upload into a library file. The real content type is read from the file (not
 * the name the browser sent) and must be accepted, within its type's size limit. Images are turned
 * upright, scaled down to config media.max_image_dimension and re-saved, which drops location and
 * camera details (animated GIFs are kept as they are).
 */
final class CompleteMediaUploadAction
{
    /**
     * @throws MediaException when the upload is unfinished, or the file isn't accepted
     */
    public function handle(User $actor, MediaUpload $upload): MediaAsset
    {
        if (! $upload->isComplete()) {
            throw MediaException::uploadIncomplete();
        }

        $path = Storage::disk('local')->path($upload->path());
        $mimeType = (string) MimeTypes::getDefault()->guessMimeType($path);
        $type = MediaType::fromMimeType($mimeType);

        if ($type === null) {
            $this->discard($upload);

            throw MediaException::typeNotAllowed();
        }

        if (filesize($path) > $type->maxBytes()) {
            $this->discard($upload);

            throw MediaException::tooLarge($type->maxBytes());
        }

        // The image library picks the format from the extension, so give the file its real one.
        $extension = MimeTypes::getDefault()->getExtensions($mimeType)[0] ?? 'bin';
        $file = $path.'.'.$extension;
        rename($path, $file);
        $path = $file;

        if ($type === MediaType::Image && $mimeType !== 'image/gif') {
            $maxDimension = (int) config('media.max_image_dimension');

            try {
                // Loading turns the image upright from its EXIF orientation; saving drops the EXIF data.
                Image::load($path)->fit(Fit::Max, $maxDimension, $maxDimension)->save();
            } catch (Throwable) {
                // Not a readable image after all (e.g. a damaged file).
                @unlink($path);
                $upload->delete();

                throw MediaException::typeNotAllowed();
            }
        }

        clearstatcache(true, $path);
        [$width, $height] = $type === MediaType::Image ? (getimagesize($path) ?: [null, null]) : [null, null];

        return DB::transaction(function () use ($actor, $upload, $path, $mimeType, $extension, $type, $width, $height): MediaAsset {
            $asset = MediaAsset::query()->create([
                'folder_id' => $upload->folder_id,
                'type' => $type,
                'name' => $upload->name,
                'mime_type' => $mimeType,
                'size' => (int) filesize($path),
                'width' => $width,
                'height' => $height,
                'uploaded_by' => $actor->id,
            ]);

            $asset->addMedia($path)
                ->usingName(pathinfo($upload->name, PATHINFO_FILENAME))
                ->usingFileName($this->fileName($upload->name, $extension))
                ->toMediaCollection(MediaAsset::FILE_COLLECTION);

            $upload->delete();

            activity('admin')
                ->causedBy($actor)
                ->performedOn($asset)
                ->event('media.uploaded')
                ->withProperties(['name' => $asset->name, 'type' => $type->value])
                ->log('media.uploaded');

            return $asset;
        });
    }

    /**
     * A safe file name for storage: the original name in Latin letters with the real extension.
     */
    private function fileName(string $name, string $extension): string
    {
        $base = Str::slug(pathinfo($name, PATHINFO_FILENAME)) ?: 'file';

        return Str::limit($base, 80, '').'.'.$extension;
    }

    private function discard(MediaUpload $upload): void
    {
        Storage::disk('local')->delete($upload->path());
        $upload->delete();
    }
}

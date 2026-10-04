<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Media;

use App\Actions\Media\AppendMediaChunkAction;
use App\Actions\Media\CancelMediaUploadAction;
use App\Actions\Media\CompleteMediaUploadAction;
use App\Actions\Media\StartMediaUploadAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Media\MediaChunkRequest;
use App\Http\Requests\Api\V1\Admin\Media\StartMediaUploadRequest;
use App\Http\Resources\V1\Admin\MediaAssetResource;
use App\Http\Resources\V1\Admin\MediaUploadResource;
use App\Models\MediaUpload;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;

/**
 * Chunked uploads: start (announce the file), send chunks in order, complete. `{mediaUpload}`
 * only resolves the current user's unexpired uploads.
 */
class MediaUploadController extends Controller
{
    public function store(StartMediaUploadRequest $request, StartMediaUploadAction $start, #[CurrentUser] User $actor): MediaUploadResource
    {
        return new MediaUploadResource($start->handle($actor, $request->toData()));
    }

    /**
     * 409 `media_chunk_out_of_order` (with `received`) when the chunk doesn't continue the file.
     */
    public function chunk(MediaChunkRequest $request, MediaUpload $mediaUpload, AppendMediaChunkAction $append): MediaUploadResource
    {
        /** @var UploadedFile $chunk */
        $chunk = $request->file('chunk');

        return new MediaUploadResource($append->handle($mediaUpload, $chunk, $request->integer('offset')));
    }

    public function complete(MediaUpload $mediaUpload, CompleteMediaUploadAction $complete, #[CurrentUser] User $actor): MediaAssetResource
    {
        $asset = $complete->handle($actor, $mediaUpload);

        return new MediaAssetResource($asset->load(MediaAssetResource::detailRelations()));
    }

    public function destroy(MediaUpload $mediaUpload, CancelMediaUploadAction $cancel): Response
    {
        $cancel->handle($mediaUpload);

        return response()->noContent();
    }
}

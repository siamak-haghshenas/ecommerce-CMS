<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Media;

use App\Actions\Media\DeleteMediaAssetAction;
use App\Actions\Media\MoveMediaAssetsAction;
use App\Actions\Media\SearchMediaAction;
use App\Actions\Media\UpdateMediaAssetAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Media\IndexMediaRequest;
use App\Http\Requests\Api\V1\Admin\Media\MoveMediaAssetsRequest;
use App\Http\Requests\Api\V1\Admin\Media\UpdateMediaAssetRequest;
use App\Http\Resources\V1\Admin\MediaAssetResource;
use App\Models\MediaAsset;
use App\Models\MediaFolder;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class MediaAssetController extends Controller
{
    public function index(IndexMediaRequest $request, SearchMediaAction $search): AnonymousResourceCollection
    {
        return MediaAssetResource::collection($search->handle($request->toFilters()));
    }

    /**
     * The details view, with "where it's used". Files in the trash too.
     */
    public function show(MediaAsset $mediaAsset): MediaAssetResource
    {
        return new MediaAssetResource($mediaAsset->load(MediaAssetResource::detailRelations()));
    }

    public function update(UpdateMediaAssetRequest $request, MediaAsset $mediaAsset, UpdateMediaAssetAction $update, #[CurrentUser] User $actor): MediaAssetResource
    {
        $update->handle($actor, $mediaAsset, $request->toData());

        return new MediaAssetResource($mediaAsset->load(MediaAssetResource::detailRelations()));
    }

    /**
     * Moves files into a folder (`folder_id` null: no folder).
     */
    public function move(MoveMediaAssetsRequest $request, MoveMediaAssetsAction $move, #[CurrentUser] User $actor): JsonResponse
    {
        $folder = $request->filled('folder_id') ? MediaFolder::query()->findOrFail($request->integer('folder_id')) : null;

        return response()->json(['data' => ['moved' => $move->handle($actor, $request->ids(), $folder)]]);
    }

    /**
     * Moves the file to the trash; 409 `media_in_use` when something uses it.
     */
    public function destroy(MediaAsset $mediaAsset, DeleteMediaAssetAction $delete, #[CurrentUser] User $actor): Response
    {
        $delete->handle($actor, $mediaAsset);

        return response()->noContent();
    }

    public function restore(MediaAsset $mediaAsset, DeleteMediaAssetAction $delete, #[CurrentUser] User $actor): MediaAssetResource
    {
        $delete->restore($actor, $mediaAsset);

        return new MediaAssetResource($mediaAsset->load(MediaAssetResource::detailRelations()));
    }
}

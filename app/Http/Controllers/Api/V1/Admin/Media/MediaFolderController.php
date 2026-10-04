<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Media;

use App\Actions\Media\DeleteMediaFolderAction;
use App\Actions\Media\ManageMediaFolderAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Media\MediaFolderRequest;
use App\Http\Resources\V1\Admin\MediaFolderResource;
use App\Models\MediaFolder;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class MediaFolderController extends Controller
{
    /**
     * Every folder by name, with its number of files (not counting the trash).
     */
    public function index(): AnonymousResourceCollection
    {
        return MediaFolderResource::collection(MediaFolder::query()->withCount('assets')->orderBy('name')->get());
    }

    public function store(MediaFolderRequest $request, ManageMediaFolderAction $manage, #[CurrentUser] User $actor): MediaFolderResource
    {
        return new MediaFolderResource($manage->handle($actor, $request->string('name')->toString())->loadCount('assets'));
    }

    public function update(MediaFolderRequest $request, MediaFolder $mediaFolder, ManageMediaFolderAction $manage, #[CurrentUser] User $actor): MediaFolderResource
    {
        return new MediaFolderResource($manage->handle($actor, $request->string('name')->toString(), $mediaFolder)->loadCount('assets'));
    }

    /**
     * The folder's files are kept and move to "no folder".
     */
    public function destroy(MediaFolder $mediaFolder, DeleteMediaFolderAction $delete, #[CurrentUser] User $actor): Response
    {
        $delete->handle($actor, $mediaFolder);

        return response()->noContent();
    }
}

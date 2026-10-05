<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Posts;

use App\Actions\Posts\DeletePostAction;
use App\Actions\Posts\SavePostAction;
use App\Actions\Posts\SearchPostsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Posts\IndexPostsRequest;
use App\Http\Requests\Api\V1\Admin\Posts\SavePostRequest;
use App\Http\Resources\V1\Admin\PostListResource;
use App\Http\Resources\V1\Admin\PostResource;
use App\Models\Post;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class PostController extends Controller
{
    public function index(IndexPostsRequest $request, SearchPostsAction $search): AnonymousResourceCollection
    {
        return PostListResource::collection($search->handle($request->toFilters()));
    }

    /**
     * Also for posts in the trash (route binding includes them), so they can be viewed and restored.
     */
    public function show(Post $post): PostResource
    {
        return new PostResource($post->load(PostResource::relations()));
    }

    public function store(SavePostRequest $request, SavePostAction $save, #[CurrentUser] User $actor): PostResource
    {
        return new PostResource($save->handle($actor, null, $request->toData())->load(PostResource::relations()));
    }

    public function update(SavePostRequest $request, Post $post, SavePostAction $save, #[CurrentUser] User $actor): PostResource
    {
        return new PostResource($save->handle($actor, $post, $request->toData())->load(PostResource::relations()));
    }

    public function destroy(Post $post, DeletePostAction $delete, #[CurrentUser] User $actor): Response
    {
        $delete->handle($actor, $post);

        return response()->noContent();
    }

    public function restore(Post $post, DeletePostAction $delete, #[CurrentUser] User $actor): PostResource
    {
        $delete->restore($actor, $post);

        return new PostResource($post->load(PostResource::relations()));
    }
}

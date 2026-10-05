<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Storefront\Blog;

use App\Actions\Posts\ResolvePostPreviewAction;
use App\Actions\Posts\SearchStorefrontPostsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Storefront\Blog\PostIndexRequest;
use App\Http\Resources\V1\Storefront\PostResource;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PostController extends Controller
{
    /**
     * Published posts, newest first, paginated (cards: no content).
     */
    public function index(PostIndexRequest $request, SearchStorefrontPostsAction $search): AnonymousResourceCollection
    {
        return PostResource::collection($search->handle($request->toFilters(), PostResource::relations()));
    }

    /**
     * One published post by slug; drafts, scheduled, trashed and unknown posts are 404.
     */
    public function show(string $slug): PostResource
    {
        return (new PostResource(Post::query()->visible()->with(PostResource::relations())->where('slug', $slug)->firstOrFail()))->detailed();
    }

    /**
     * A post as a staff member previews it (any status, their unsaved changes included). Never
     * cached or indexed; invalid or expired tokens are 404.
     */
    public function preview(string $token, ResolvePostPreviewAction $resolve): JsonResponse
    {
        return (new PostResource($resolve->handle($token)))->detailed()
            ->response()
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Storefront\Content;

use App\Actions\Pages\ResolvePagePreviewAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Storefront\PageResource;
use App\Models\Page;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PageController extends Controller
{
    /**
     * Every published page (not paginated: stores have a handful), for footer links and sitemaps.
     */
    public function index(): AnonymousResourceCollection
    {
        return PageResource::collection(Page::query()->visible()->orderBy('title')->get());
    }

    /**
     * One published page by slug; drafts, scheduled, trashed and unknown pages are 404.
     */
    public function show(string $slug): PageResource
    {
        return (new PageResource(Page::query()->visible()->with(['imageAssets.media', 'ogImageAssets.media'])->where('slug', $slug)->firstOrFail()))->detailed();
    }

    /**
     * A page as a staff member previews it (any status, their unsaved changes included). Never
     * cached or indexed; invalid or expired tokens are 404.
     */
    public function preview(string $token, ResolvePagePreviewAction $resolve): JsonResponse
    {
        return (new PageResource($resolve->handle($token)))->detailed()
            ->response()
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}

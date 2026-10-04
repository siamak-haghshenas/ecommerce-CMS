<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Storefront\Content;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Storefront\PageResource;
use App\Models\Page;
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
        return (new PageResource(Page::query()->visible()->with('imageAssets.media')->where('slug', $slug)->firstOrFail()))->detailed();
    }
}

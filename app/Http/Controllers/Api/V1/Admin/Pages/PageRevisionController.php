<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Pages;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Admin\PageRevisionResource;
use App\Models\Page;
use App\Models\PageRevision;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PageRevisionController extends Controller
{
    /**
     * The page's kept revisions, newest first (without their content).
     */
    public function index(Page $page): AnonymousResourceCollection
    {
        return PageRevisionResource::collection($page->revisions()->with('saver')->get());
    }

    /**
     * One revision with every saved field, to preview it and load it into the editor.
     */
    public function show(Page $page, PageRevision $revision): PageRevisionResource
    {
        return (new PageRevisionResource($revision->load(['saver', 'image.media', 'ogImage.media'])))->detailed();
    }
}

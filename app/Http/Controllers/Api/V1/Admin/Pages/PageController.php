<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Pages;

use App\Actions\Pages\DeletePageAction;
use App\Actions\Pages\SavePageAction;
use App\Actions\Pages\SearchPagesAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Pages\IndexPagesRequest;
use App\Http\Requests\Api\V1\Admin\Pages\SavePageRequest;
use App\Http\Resources\V1\Admin\PageListResource;
use App\Http\Resources\V1\Admin\PageResource;
use App\Models\Page;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class PageController extends Controller
{
    public function index(IndexPagesRequest $request, SearchPagesAction $search): AnonymousResourceCollection
    {
        return PageListResource::collection($search->handle($request->toFilters()));
    }

    /**
     * Also for pages in the trash (route binding includes them), so they can be viewed and restored.
     */
    public function show(Page $page): PageResource
    {
        return new PageResource($page->load(PageResource::relations()));
    }

    public function store(SavePageRequest $request, SavePageAction $save, #[CurrentUser] User $actor): PageResource
    {
        return new PageResource($save->handle($actor, null, $request->toData())->load(PageResource::relations()));
    }

    public function update(SavePageRequest $request, Page $page, SavePageAction $save, #[CurrentUser] User $actor): PageResource
    {
        return new PageResource($save->handle($actor, $page, $request->toData())->load(PageResource::relations()));
    }

    public function destroy(Page $page, DeletePageAction $delete, #[CurrentUser] User $actor): Response
    {
        $delete->handle($actor, $page);

        return response()->noContent();
    }

    public function restore(Page $page, DeletePageAction $delete, #[CurrentUser] User $actor): PageResource
    {
        $delete->restore($actor, $page);

        return new PageResource($page->load(PageResource::relations()));
    }
}

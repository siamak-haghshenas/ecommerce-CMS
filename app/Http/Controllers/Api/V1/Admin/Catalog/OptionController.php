<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Catalog;

use App\Actions\Catalog\DeleteCatalogItemAction;
use App\Actions\Catalog\ReorderListAction;
use App\Actions\Catalog\SaveOptionAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Catalog\ReorderIdsRequest;
use App\Http\Requests\Api\V1\Admin\Catalog\SaveOptionRequest;
use App\Http\Resources\V1\Admin\OptionResource;
use App\Models\Option;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class OptionController extends Controller
{
    /**
     * Every option in manual order, with values. Not paginated: admins order the whole list.
     */
    public function index(): AnonymousResourceCollection
    {
        return OptionResource::collection(Option::query()->with('values')->ordered()->get());
    }

    public function store(SaveOptionRequest $request, SaveOptionAction $save, #[CurrentUser] User $actor): OptionResource
    {
        return new OptionResource($save->handle($actor, null, $request->toData()));
    }

    public function update(SaveOptionRequest $request, Option $option, SaveOptionAction $save, #[CurrentUser] User $actor): OptionResource
    {
        return new OptionResource($save->handle($actor, $option, $request->toData()));
    }

    public function destroy(Option $option, DeleteCatalogItemAction $delete, #[CurrentUser] User $actor): Response
    {
        $delete->handle($actor, $option);

        return response()->noContent();
    }

    public function reorder(ReorderIdsRequest $request, ReorderListAction $reorder, #[CurrentUser] User $actor): AnonymousResourceCollection
    {
        $reorder->handle($actor, Option::class, $request->ids(), 'option.reordered');

        return $this->index();
    }
}

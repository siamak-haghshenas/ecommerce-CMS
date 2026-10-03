<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Catalog;

use App\Actions\Catalog\DeleteCatalogItemAction;
use App\Actions\Catalog\ReorderListAction;
use App\Actions\Catalog\SaveAttributeAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Catalog\ReorderIdsRequest;
use App\Http\Requests\Api\V1\Admin\Catalog\SaveAttributeRequest;
use App\Http\Resources\V1\Admin\AttributeResource;
use App\Models\Attribute;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class AttributeController extends Controller
{
    /**
     * Every attribute in manual order, with values. Not paginated: admins order the whole list.
     */
    public function index(): AnonymousResourceCollection
    {
        return AttributeResource::collection(Attribute::query()->with('values')->withCount('categories')->ordered()->get());
    }

    public function store(SaveAttributeRequest $request, SaveAttributeAction $save, #[CurrentUser] User $actor): AttributeResource
    {
        return new AttributeResource($save->handle($actor, null, $request->toData()));
    }

    public function update(SaveAttributeRequest $request, Attribute $attribute, SaveAttributeAction $save, #[CurrentUser] User $actor): AttributeResource
    {
        return new AttributeResource($save->handle($actor, $attribute, $request->toData()));
    }

    public function destroy(Attribute $attribute, DeleteCatalogItemAction $delete, #[CurrentUser] User $actor): Response
    {
        $delete->handle($actor, $attribute);

        return response()->noContent();
    }

    public function reorder(ReorderIdsRequest $request, ReorderListAction $reorder, #[CurrentUser] User $actor): AnonymousResourceCollection
    {
        $reorder->handle($actor, Attribute::class, $request->ids(), 'attribute.reordered');

        return $this->index();
    }
}

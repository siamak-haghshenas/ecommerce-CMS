<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Shipping;

use App\Actions\Shipping\CountUnmeasuredProductsAction;
use App\Actions\Shipping\DeleteShippingMethodAction;
use App\Actions\Shipping\ListShippingMethodsAction;
use App\Actions\Shipping\SaveShippingMethodAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Shipping\SaveShippingMethodRequest;
use App\Http\Resources\V1\Admin\ShippingMethodResource;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ShippingMethodController extends Controller
{
    /**
     * Every method in the order customers see them (not paginated); `meta.unmeasured_products`
     * counts products without a weight or missing a dimension.
     */
    public function index(ListShippingMethodsAction $list, CountUnmeasuredProductsAction $count): AnonymousResourceCollection
    {
        return ShippingMethodResource::collection($list->handle())
            ->additional(['meta' => ['unmeasured_products' => $count->handle()]]);
    }

    public function show(ShippingMethod $shippingMethod): ShippingMethodResource
    {
        return new ShippingMethodResource($shippingMethod->load(['rates', 'cities.province']));
    }

    public function store(SaveShippingMethodRequest $request, SaveShippingMethodAction $save, #[CurrentUser] User $actor): ShippingMethodResource
    {
        return $this->show($save->handle($actor, null, $request->toData()));
    }

    public function update(SaveShippingMethodRequest $request, ShippingMethod $shippingMethod, SaveShippingMethodAction $save, #[CurrentUser] User $actor): ShippingMethodResource
    {
        return $this->show($save->handle($actor, $shippingMethod, $request->toData()));
    }

    public function destroy(ShippingMethod $shippingMethod, DeleteShippingMethodAction $delete, #[CurrentUser] User $actor): Response
    {
        $delete->handle($actor, $shippingMethod);

        return response()->noContent();
    }
}

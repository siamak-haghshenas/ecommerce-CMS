<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Shipping;

use App\Actions\Shipping\ListShippingMethodsAction;
use App\Actions\Shipping\ReorderShippingMethodsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Shipping\ReorderShippingMethodsRequest;
use App\Http\Resources\V1\Admin\ShippingMethodResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ShippingMethodOrderController extends Controller
{
    /**
     * Save the checkout order (drag and drop, move up/down) and return the reordered list.
     */
    public function __invoke(ReorderShippingMethodsRequest $request, ReorderShippingMethodsAction $reorder, ListShippingMethodsAction $list, #[CurrentUser] User $actor): AnonymousResourceCollection
    {
        $reorder->handle($actor, $request->ids());

        return ShippingMethodResource::collection($list->handle());
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Shipping;

use App\Actions\Shipping\SimulateShippingAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Shipping\SimulateShippingRequest;
use App\Http\Resources\V1\Admin\ShippingSimulationResource;

class ShippingSimulationController extends Controller
{
    /**
     * The editor's test box: the unsaved method priced for sample items. Nothing is saved.
     */
    public function __invoke(SimulateShippingRequest $request, SimulateShippingAction $simulate): ShippingSimulationResource
    {
        return new ShippingSimulationResource($simulate->handle($request->terms(), $request->cartItems(), $request->cityId(), $request->coupon()));
    }
}

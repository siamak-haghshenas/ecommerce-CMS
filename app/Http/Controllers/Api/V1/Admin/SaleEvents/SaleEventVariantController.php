<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\SaleEvents;

use App\Actions\SaleEvents\FindEventVariantsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\SaleEvents\SaleEventVariantsRequest;
use App\Http\Resources\V1\Admin\SaleEventVariantResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SaleEventVariantController extends Controller
{
    /**
     * The enabled variants of the chosen products, as the sale event editor adds them.
     */
    public function __invoke(SaleEventVariantsRequest $request, FindEventVariantsAction $find): AnonymousResourceCollection
    {
        return SaleEventVariantResource::collection($find->handle($request->productIds()));
    }
}

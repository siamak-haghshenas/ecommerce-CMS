<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Shipping;

use App\Actions\Shipping\ListProvincesAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ProvinceResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProvinceController extends Controller
{
    /**
     * Every province with its cities, for the courier city picker.
     */
    public function __invoke(ListProvincesAction $list): AnonymousResourceCollection
    {
        return ProvinceResource::collection($list->handle());
    }
}

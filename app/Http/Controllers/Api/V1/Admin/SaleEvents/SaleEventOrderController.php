<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\SaleEvents;

use App\Actions\SaleEvents\ReorderSaleEventsAction;
use App\Actions\SaleEvents\SearchSaleEventsAction;
use App\Data\SaleEvents\SaleEventFilters;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\SaleEvents\ReorderSaleEventsRequest;
use App\Http\Resources\V1\Admin\SaleEventResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SaleEventOrderController extends Controller
{
    /**
     * Save a new priority order (drag and drop, move up/down) and return the reordered list.
     */
    public function __invoke(ReorderSaleEventsRequest $request, ReorderSaleEventsAction $reorder, SearchSaleEventsAction $search, #[CurrentUser] User $actor): AnonymousResourceCollection
    {
        $reorder->handle($actor, $request->ids());

        return SaleEventResource::collection($search->handle(new SaleEventFilters));
    }
}

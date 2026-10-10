<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\SaleEvents;

use App\Actions\SaleEvents\DeleteSaleEventAction;
use App\Actions\SaleEvents\FindOverridingEventsAction;
use App\Actions\SaleEvents\SaveSaleEventAction;
use App\Actions\SaleEvents\SearchSaleEventsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\SaleEvents\IndexSaleEventsRequest;
use App\Http\Requests\Api\V1\Admin\SaleEvents\SaveSaleEventRequest;
use App\Http\Resources\V1\Admin\SaleEventResource;
use App\Models\SaleEvent;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class SaleEventController extends Controller
{
    /**
     * Every event in priority order (filtered by the search and status).
     */
    public function index(IndexSaleEventsRequest $request, SearchSaleEventsAction $search): AnonymousResourceCollection
    {
        return SaleEventResource::collection($search->handle($request->toFilters()));
    }

    /**
     * The event with every variant and its landing page.
     */
    public function show(SaleEvent $saleEvent, FindOverridingEventsAction $overriding): SaleEventResource
    {
        return $this->detail($saleEvent, $overriding);
    }

    public function store(SaveSaleEventRequest $request, SaveSaleEventAction $save, FindOverridingEventsAction $overriding, #[CurrentUser] User $actor): SaleEventResource
    {
        return $this->detail($save->handle($actor, null, $request->toData()), $overriding);
    }

    public function update(SaveSaleEventRequest $request, SaleEvent $saleEvent, SaveSaleEventAction $save, FindOverridingEventsAction $overriding, #[CurrentUser] User $actor): SaleEventResource
    {
        return $this->detail($save->handle($actor, $saleEvent, $request->toData()), $overriding);
    }

    public function destroy(SaleEvent $saleEvent, DeleteSaleEventAction $delete, #[CurrentUser] User $actor): Response
    {
        $delete->handle($actor, $saleEvent);

        return response()->noContent();
    }

    private function detail(SaleEvent $saleEvent, FindOverridingEventsAction $overriding): SaleEventResource
    {
        $saleEvent->load(SaleEventResource::detailRelations());

        return (new SaleEventResource($saleEvent))->withItems($overriding->handle($saleEvent));
    }
}

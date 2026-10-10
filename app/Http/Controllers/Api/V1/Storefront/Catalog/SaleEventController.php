<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Storefront\Catalog;

use App\Actions\SaleEvents\ResolveSaleEventPreviewAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Storefront\SaleEventResource;
use App\Models\SaleEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SaleEventController extends Controller
{
    /**
     * Landing pages of events that haven't ended (upcoming and running), highest priority first.
     */
    public function index(): AnonymousResourceCollection
    {
        return SaleEventResource::collection(SaleEvent::query()
            ->withVisibleLanding()
            ->where('ends_at', '>', now())
            ->with('bannerAssets.media')
            ->orderBy('position')
            ->orderBy('id')
            ->get());
    }

    /**
     * One landing page by slug, before, during and after the event; switched off, disabled or
     * unknown events are 404.
     */
    public function show(string $slug): SaleEventResource
    {
        $saleEvent = SaleEvent::query()->withVisibleLanding()->where('slug', $slug)->with(['bannerAssets.media', 'ogImageAssets.media'])->firstOrFail();

        return (new SaleEventResource($saleEvent))->detailed();
    }

    /**
     * The landing page as a staff member previews it, whatever the event's state. Never cached or
     * indexed; invalid or expired tokens are 404.
     */
    public function preview(string $token, ResolveSaleEventPreviewAction $resolve): JsonResponse
    {
        return (new SaleEventResource($resolve->handle($token)))->detailed()
            ->response()
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}

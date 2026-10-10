<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\SaleEvents;

use App\Actions\SaleEvents\PreviewSaleEventAction;
use App\Http\Controllers\Controller;
use App\Models\SaleEvent;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

class SaleEventPreviewController extends Controller
{
    /**
     * A preview token (60 minutes) for the landing page and the storefront address that shows it, if one is set.
     */
    public function __invoke(SaleEvent $saleEvent, PreviewSaleEventAction $preview, #[CurrentUser] User $actor): JsonResponse
    {
        $result = $preview->handle($actor, $saleEvent);

        return response()->json(['data' => [
            'token' => $result->token,
            'url' => $result->url,
            'expires_at' => $result->expiresAt->toIso8601String(),
        ]], 201);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Pages;

use App\Actions\Pages\PreviewPageAction;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

class PagePreviewController extends Controller
{
    /**
     * A preview token (60 minutes) and the storefront address that shows it, if one is set.
     */
    public function __invoke(Page $page, PreviewPageAction $preview, #[CurrentUser] User $actor): JsonResponse
    {
        $result = $preview->handle($actor, $page);

        return response()->json(['data' => [
            'token' => $result->token,
            'url' => $result->url,
            'expires_at' => $result->expiresAt->toIso8601String(),
        ]], 201);
    }
}

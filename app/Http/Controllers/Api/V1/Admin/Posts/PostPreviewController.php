<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Posts;

use App\Actions\Posts\PreviewPostAction;
use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

class PostPreviewController extends Controller
{
    /**
     * A preview token (60 minutes) and the storefront address that shows it, if one is set.
     */
    public function __invoke(Post $post, PreviewPostAction $preview, #[CurrentUser] User $actor): JsonResponse
    {
        $result = $preview->handle($actor, $post);

        return response()->json(['data' => [
            'token' => $result->token,
            'url' => $result->url,
            'expires_at' => $result->expiresAt->toIso8601String(),
        ]], 201);
    }
}

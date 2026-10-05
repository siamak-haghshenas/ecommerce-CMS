<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Account;

use App\Actions\Account\UpdateBylineAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Account\UpdateBylineRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/**
 * The signed-in staff member's own author byline (each edits only their own).
 */
class BylineController extends Controller
{
    public function show(#[CurrentUser] User $user): JsonResponse
    {
        return response()->json(['data' => $this->byline($user->load('avatarAssets.media'))]);
    }

    public function update(UpdateBylineRequest $request, UpdateBylineAction $update, #[CurrentUser] User $user): JsonResponse
    {
        return response()->json(['data' => $this->byline($update->handle($user, $request->toData())->load('avatarAssets.media'))]);
    }

    /**
     * @return array<string, mixed>
     */
    private function byline(User $user): array
    {
        return [
            'author_name' => $user->author_name,
            'slug' => $user->author_slug,
            'author_bio' => $user->author_bio,
            'avatar' => $user->avatarAssets->first()?->picture(),
        ];
    }
}

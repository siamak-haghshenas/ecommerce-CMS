<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Posts;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class PostAuthorController extends Controller
{
    /**
     * Staff who can be a post's author (for the author picker and the list filter), with whether
     * storefronts will show them (only with a public byline).
     */
    public function __invoke(): JsonResponse
    {
        $staff = User::query()->staff()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'author_name', 'author_slug']);

        return response()->json(['data' => $staff->map(fn (User $user): array => [
            'id' => $user->id,
            'name' => $user->name,
            'author_name' => $user->author_name,
            'has_byline' => $user->hasByline(),
        ])->all()]);
    }
}

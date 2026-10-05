<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Posts;

use App\Actions\Posts\SavePostAutosaveAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Posts\AutosavePostRequest;
use App\Models\Post;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class PostAutosaveController extends Controller
{
    /**
     * Keep the editor's unsaved form (sent every 30 seconds while there are changes).
     */
    public function update(AutosavePostRequest $request, Post $post, SavePostAutosaveAction $autosave, #[CurrentUser] User $actor): JsonResponse
    {
        $saved = $autosave->handle($actor, $post, $request->toData());

        return response()->json(['data' => ['saved_at' => $saved->updated_at?->toIso8601String()]]);
    }

    /**
     * "Discard": forget the staff member's unsaved form.
     */
    public function destroy(Post $post, SavePostAutosaveAction $autosave, #[CurrentUser] User $actor): Response
    {
        $autosave->discard($actor, $post);

        return response()->noContent();
    }
}

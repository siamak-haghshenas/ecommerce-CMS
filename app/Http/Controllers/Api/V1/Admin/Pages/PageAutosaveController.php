<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Pages;

use App\Actions\Pages\SavePageAutosaveAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Pages\AutosavePageRequest;
use App\Models\Page;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class PageAutosaveController extends Controller
{
    /**
     * Keep the editor's unsaved form (sent every 30 seconds while there are changes).
     */
    public function update(AutosavePageRequest $request, Page $page, SavePageAutosaveAction $autosave, #[CurrentUser] User $actor): JsonResponse
    {
        $saved = $autosave->handle($actor, $page, $request->toData());

        return response()->json(['data' => ['saved_at' => $saved->updated_at?->toIso8601String()]]);
    }

    /**
     * "Discard": forget the staff member's unsaved form.
     */
    public function destroy(Page $page, SavePageAutosaveAction $autosave, #[CurrentUser] User $actor): Response
    {
        $autosave->discard($actor, $page);

        return response()->noContent();
    }
}

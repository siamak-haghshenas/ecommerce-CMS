<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Comments;

use App\Actions\Comments\CountCommentStatesAction;
use App\Actions\Comments\DeleteCommentsAction;
use App\Actions\Comments\SearchCommentsAction;
use App\Enums\CommentableType;
use App\Enums\CommentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Comments\BulkDeleteCommentsRequest;
use App\Http\Requests\Api\V1\Admin\Comments\IndexCommentsRequest;
use App\Http\Resources\V1\Admin\CommentResource;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CommentController extends Controller
{
    /**
     * Comments to moderate (pending by default), newest first; `meta.status_counts` for the tabs.
     */
    public function index(IndexCommentsRequest $request, SearchCommentsAction $search, CountCommentStatesAction $count): AnonymousResourceCollection
    {
        return CommentResource::collection($search->handle($request->toFilters()))
            ->additional(['meta' => ['status_counts' => $count->handle($request->search(), $request->type(), $request->itemId())]]);
    }

    /**
     * How many comments wait for moderation, per kind (the menu badges of Blog comments and Product comments).
     */
    public function pendingCount(CountCommentStatesAction $count): JsonResponse
    {
        $pending = [];

        foreach (CommentableType::cases() as $type) {
            $pending[$type->value] = $count->handle(type: $type)[CommentStatus::Pending->value];
        }

        return response()->json(['data' => ['pending' => $pending]]);
    }

    public function destroy(Comment $comment, DeleteCommentsAction $delete, #[CurrentUser] User $actor): Response
    {
        $delete->handle($actor, [$comment]);

        return response()->noContent();
    }

    /**
     * Delete several comments for good.
     */
    public function bulkDestroy(BulkDeleteCommentsRequest $request, DeleteCommentsAction $delete, #[CurrentUser] User $actor): JsonResponse
    {
        $deleted = $delete->handle($actor, Comment::query()->whereKey($request->ids())->get());

        return response()->json(['data' => ['deleted' => $deleted]]);
    }
}

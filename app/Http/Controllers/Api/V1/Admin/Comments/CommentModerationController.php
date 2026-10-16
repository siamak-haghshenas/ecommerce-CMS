<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Comments;

use App\Actions\Comments\ModerateCommentsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Comments\BulkModerateCommentsRequest;
use App\Http\Requests\Api\V1\Admin\Comments\ModerateCommentRequest;
use App\Http\Resources\V1\Admin\CommentResource;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

class CommentModerationController extends Controller
{
    /**
     * Approve, reject or mark one comment as spam; the same status again is 409 `comment_status_unchanged`.
     */
    public function update(ModerateCommentRequest $request, Comment $comment, ModerateCommentsAction $moderate, #[CurrentUser] User $actor): CommentResource
    {
        $moderate->handle($actor, [$comment], $request->toData(), strict: true);

        return new CommentResource($comment->load(CommentResource::relations()));
    }

    /**
     * The same status for several comments, all or nothing; comments that already have it are left
     * as they are (`changed` counts the others).
     */
    public function bulk(BulkModerateCommentsRequest $request, ModerateCommentsAction $moderate, #[CurrentUser] User $actor): JsonResponse
    {
        $changed = $moderate->handle($actor, Comment::query()->whereKey($request->ids())->get(), $request->toData());

        return response()->json(['data' => ['changed' => count($changed)]]);
    }
}

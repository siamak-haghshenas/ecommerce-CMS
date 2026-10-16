<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Comments;

use App\Actions\Comments\ReplyToCommentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Comments\ReplyToCommentRequest;
use App\Http\Resources\V1\Admin\CommentResource;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;

class CommentReplyController extends Controller
{
    /**
     * Write or change the store's answer (shown once the comment is approved).
     */
    public function update(ReplyToCommentRequest $request, Comment $comment, ReplyToCommentAction $reply, #[CurrentUser] User $actor): CommentResource
    {
        return new CommentResource($reply->handle($actor, $comment, $request->reply())->load(CommentResource::relations()));
    }

    public function destroy(Comment $comment, ReplyToCommentAction $reply, #[CurrentUser] User $actor): CommentResource
    {
        return new CommentResource($reply->handle($actor, $comment, null)->load(CommentResource::relations()));
    }
}

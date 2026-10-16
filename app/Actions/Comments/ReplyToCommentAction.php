<?php

declare(strict_types=1);

namespace App\Actions\Comments;

use App\Models\Comment;
use App\Models\User;

final class ReplyToCommentAction
{
    /**
     * Write, change or (null) remove the store's answer to a comment. It is shown under the
     * comment once the comment is approved, as the store's answer, never with a staff name.
     */
    public function handle(User $actor, Comment $comment, ?string $reply): Comment
    {
        if ($reply === $comment->reply) {
            return $comment;
        }

        $comment->forceFill([
            'reply' => $reply,
            'replied_by' => $reply === null ? null : $actor->id,
            'replied_at' => $reply === null ? null : now(),
        ])->save();

        $event = $reply === null ? 'comment.reply_deleted' : 'comment.replied';

        activity('admin')
            ->causedBy($actor)
            ->performedOn($comment)
            ->event($event)
            ->withProperties($comment->auditItem())
            ->log($event);

        return $comment;
    }
}

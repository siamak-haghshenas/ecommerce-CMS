<?php

declare(strict_types=1);

namespace App\Actions\Comments;

use App\Data\Comments\CommentData;
use App\Exceptions\CommentException;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Product;
use App\Models\User;

final class AddCommentAction
{
    /**
     * A customer's comment on a visible post or product. It always starts pending: whatever the
     * request sends, only staff change the status.
     *
     * @throws CommentException
     */
    public function handle(User $customer, Post|Product $item, CommentData $data): Comment
    {
        if (! $item->acceptsComments()) {
            throw CommentException::closed();
        }

        $comment = new Comment([
            'user_id' => $customer->id,
            'display_name' => $data->displayName,
            'body' => $data->body,
        ]);
        $comment->commentable()->associate($item);
        $comment->save();

        return $comment->refresh();
    }
}

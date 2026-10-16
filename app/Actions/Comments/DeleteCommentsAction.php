<?php

declare(strict_types=1);

namespace App\Actions\Comments;

use App\Models\Comment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class DeleteCommentsAction
{
    /**
     * Remove comments for good (one or a bulk selection).
     *
     * @param  iterable<Comment>  $comments
     */
    public function handle(User $actor, iterable $comments): int
    {
        return DB::transaction(function () use ($actor, $comments): int {
            $count = 0;

            foreach ($comments as $comment) {
                // Ids only: the entry stays readable without keeping the customer's words.
                activity('admin')
                    ->causedBy($actor)
                    ->performedOn($comment)
                    ->event('comment.deleted')
                    ->withProperties([...$comment->auditItem(), 'status' => $comment->status->value])
                    ->log('comment.deleted');

                $comment->delete();
                $count++;
            }

            return $count;
        });
    }
}

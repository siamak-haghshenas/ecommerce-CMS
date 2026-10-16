<?php

declare(strict_types=1);

namespace App\Actions\Comments;

use App\Data\Comments\ModerationData;
use App\Enums\CommentStatus;
use App\Exceptions\CommentException;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ModerateCommentsAction
{
    /**
     * Approve, reject or mark as spam: records the moderator and the time; the reason stays only
     * while rejected. All or nothing: a comment of the moderator's own refuses the whole step. A
     * comment that already has the status (and reason) is left as it is; with $strict (one
     * comment) that is refused instead.
     *
     * @param  iterable<Comment>  $comments
     * @return list<Comment> the comments that changed
     *
     * @throws CommentException
     */
    public function handle(User $actor, iterable $comments, ModerationData $data, bool $strict = false): array
    {
        $reason = $data->status === CommentStatus::Rejected ? $data->reason : null;
        $changed = [];

        foreach ($comments as $comment) {
            if ($comment->user_id === $actor->id) {
                throw CommentException::ownComment();
            }

            if ($comment->status === $data->status && $comment->rejection_reason === $reason) {
                if ($strict) {
                    throw CommentException::statusUnchanged();
                }

                continue;
            }

            $changed[] = $comment;
        }

        DB::transaction(function () use ($actor, $changed, $data, $reason): void {
            foreach ($changed as $comment) {
                $from = $comment->status;

                $comment->forceFill([
                    'status' => $data->status,
                    'rejection_reason' => $reason,
                    'moderated_by' => $actor->id,
                    'moderated_at' => now(),
                ])->save();

                // Ids and statuses only: never the text, the name or the reason.
                activity('admin')
                    ->causedBy($actor)
                    ->performedOn($comment)
                    ->event($data->status->auditEvent())
                    ->withProperties([...$comment->auditItem(), 'from' => $from->value, 'to' => $data->status->value])
                    ->log($data->status->auditEvent());
            }
        });

        return $changed;
    }
}

<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a blog comment stands. New comments are pending; only approved ones are public. Staff
 * move a comment between approved, rejected and spam; nothing goes back to pending.
 */
enum CommentStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Spam = 'spam';

    /**
     * The statuses staff can set.
     *
     * @return list<self>
     */
    public static function moderated(): array
    {
        return [self::Approved, self::Rejected, self::Spam];
    }

    /**
     * The audit log event of moving a comment to this status.
     */
    public function auditEvent(): string
    {
        return match ($this) {
            self::Approved => 'comment.approved',
            self::Rejected => 'comment.rejected',
            self::Spam => 'comment.spammed',
            self::Pending => 'comment.pending',
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Data\Comments;

use App\Enums\CommentStatus;

/**
 * A moderation step: the new status and, for rejected only, a reason for staff.
 */
final readonly class ModerationData
{
    public function __construct(
        public CommentStatus $status,
        public ?string $reason,
    ) {}
}

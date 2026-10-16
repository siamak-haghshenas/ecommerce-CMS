<?php

declare(strict_types=1);

namespace App\Data\Comments;

/**
 * A comment as a customer writes it (plain text).
 */
final readonly class CommentData
{
    public function __construct(
        public string $displayName,
        public string $body,
    ) {}
}

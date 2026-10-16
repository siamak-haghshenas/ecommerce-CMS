<?php

declare(strict_types=1);

namespace App\Data\Comments;

use App\Enums\CommentableType;
use App\Enums\CommentStatus;

/**
 * The admin comment list's filters.
 */
final readonly class CommentFilters
{
    /**
     * @param  CommentStatus|null  $status  null: every status
     * @param  CommentableType|null  $type  null: posts and products
     * @param  int|null  $itemId  one post or product (with $type)
     */
    public function __construct(
        public ?string $search,
        public ?CommentStatus $status,
        public ?CommentableType $type,
        public ?int $itemId,
        public int $perPage,
    ) {}
}

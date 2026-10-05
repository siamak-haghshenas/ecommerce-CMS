<?php

declare(strict_types=1);

namespace App\Data\Posts;

/**
 * Filters of the admin post list.
 */
final readonly class PostFilters
{
    public function __construct(
        public ?string $search = null,
        /** "draft", "published" (visible now) or "scheduled". */
        public ?string $state = null,
        /** A blog category, its sub-categories included. */
        public ?int $categoryId = null,
        public ?int $tagId = null,
        public ?int $authorId = null,
        public bool $needsSeo = false,
        /** Show the trash instead. */
        public bool $trashed = false,
        public string $sort = 'updated',
        public int $perPage = 20,
    ) {}
}

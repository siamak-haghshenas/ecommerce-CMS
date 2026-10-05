<?php

declare(strict_types=1);

namespace App\Data\Posts;

/**
 * The storefront post list's filters (slugs as storefronts send them).
 */
final readonly class StorefrontPostFilters
{
    public function __construct(
        public ?string $search = null,
        /** A blog category slug; its visible sub-categories are included. */
        public ?string $category = null,
        public ?string $tag = null,
        /** An author byline slug. */
        public ?string $author = null,
        public int $perPage = 12,
    ) {}
}

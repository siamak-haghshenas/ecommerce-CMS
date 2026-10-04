<?php

declare(strict_types=1);

namespace App\Data\Pages;

/**
 * Filters of the admin page list.
 */
final readonly class PageFilters
{
    public function __construct(
        public ?string $search = null,
        /** "draft", "published" (visible now) or "scheduled". */
        public ?string $state = null,
        public bool $needsSeo = false,
        /** Show the trash instead. */
        public bool $trashed = false,
        public string $sort = 'updated',
        public int $perPage = 20,
    ) {}
}

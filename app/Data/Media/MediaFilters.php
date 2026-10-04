<?php

declare(strict_types=1);

namespace App\Data\Media;

use App\Enums\MediaType;

/**
 * Filters of the media library.
 */
final readonly class MediaFilters
{
    public function __construct(
        public ?string $search = null,
        public ?MediaType $type = null,
        /** A folder id, "none" (files in no folder) or null (every file). */
        public int|string|null $folder = null,
        /** Used somewhere (true), nowhere (false) or either (null). */
        public ?bool $used = null,
        /** Show the trash instead. */
        public bool $trashed = false,
        /** "newest", "oldest", "name" or "size". */
        public string $sort = 'newest',
        public int $perPage = 40,
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Data\Account;

/**
 * A staff member's public author byline; all null removes it.
 */
final readonly class BylineData
{
    public function __construct(
        public ?string $name,
        public ?string $slug,
        public ?string $bio,
        /** A media library image, or null. */
        public ?int $avatarId,
    ) {}
}

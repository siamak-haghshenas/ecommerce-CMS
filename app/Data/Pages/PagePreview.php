<?php

declare(strict_types=1);

namespace App\Data\Pages;

use Carbon\CarbonImmutable;

final readonly class PagePreview
{
    public function __construct(
        public string $token,
        /** The store's preview address with the token filled in, or null when none is set. */
        public ?string $url,
        public CarbonImmutable $expiresAt,
    ) {}
}

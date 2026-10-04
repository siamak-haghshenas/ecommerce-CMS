<?php

declare(strict_types=1);

namespace App\Data\Brands;

final readonly class BrandData
{
    public function __construct(
        public string $name,
        public ?string $persianName,
        public string $slug,
        public ?string $description,
        public ?string $website,
        public ?string $metaTitle,
        public ?string $metaDescription,
        public ?string $focusKeyphrase,
        public bool $isActive,
        /** The logo: a media library image, or null. */
        public ?int $logoId = null,
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Data\Categories;

final readonly class CategoryData
{
    public function __construct(
        public string $name,
        public string $slug,
        public ?int $parentId,
        public ?string $description,
        public ?string $metaTitle,
        public ?string $metaDescription,
        public ?string $focusKeyphrase,
        public bool $isActive,
        /** The image: a media library image, or null. */
        public ?int $imageId = null,
    ) {}
}

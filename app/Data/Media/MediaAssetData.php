<?php

declare(strict_types=1);

namespace App\Data\Media;

/**
 * The editable details of a library file.
 */
final readonly class MediaAssetData
{
    public function __construct(
        public ?string $title,
        public ?string $alt,
        /** A video's cover picture (an image of the library). */
        public ?int $coverId,
    ) {}
}

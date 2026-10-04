<?php

declare(strict_types=1);

namespace App\Data\Media;

/**
 * A file about to be uploaded in chunks, as announced by the browser (checked again on completion).
 */
final readonly class MediaUploadData
{
    public function __construct(
        public string $name,
        public string $mimeType,
        public int $size,
        public ?int $folderId,
    ) {}
}

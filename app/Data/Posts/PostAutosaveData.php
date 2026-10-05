<?php

declare(strict_types=1);

namespace App\Data\Posts;

/**
 * The editor's form as it is, possibly incomplete (an autosave is not validated like a save).
 */
final readonly class PostAutosaveData
{
    public function __construct(
        public ?string $title,
        public ?string $slug,
        public ?string $excerpt,
        /** Sanitized rich text (RichText::sanitize). */
        public ?string $content,
        public ?int $imageId,
        public ?string $metaTitle,
        public ?string $metaDescription,
        public ?string $focusKeyphrase,
        public bool $robotsNoindex,
        public ?string $canonicalUrl,
        public ?string $ogTitle,
        public ?string $ogDescription,
        public ?int $ogImageId,
    ) {}
}

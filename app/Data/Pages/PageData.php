<?php

declare(strict_types=1);

namespace App\Data\Pages;

use App\Enums\PageStatus;
use Carbon\CarbonImmutable;

final readonly class PageData
{
    public function __construct(
        public string $title,
        public string $slug,
        public ?string $excerpt,
        /** Sanitized rich text (RichText::sanitize). */
        public ?string $content,
        public PageStatus $status,
        /** Null: now when publishing (or the page's earlier date). */
        public ?CarbonImmutable $publishedAt,
        public ?string $metaTitle,
        public ?string $metaDescription,
        public ?string $focusKeyphrase,
        /** The featured image: a media library image, or null. */
        public ?int $imageId = null,
        public bool $robotsNoindex = false,
        /** Absolute http/https address of the original, when this page repeats content found elsewhere. */
        public ?string $canonicalUrl = null,
        public ?string $ogTitle = null,
        public ?string $ogDescription = null,
        /** The Open Graph image: a media library image, or null (storefronts then use the featured image). */
        public ?int $ogImageId = null,
    ) {}
}

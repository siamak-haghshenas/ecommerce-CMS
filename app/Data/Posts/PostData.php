<?php

declare(strict_types=1);

namespace App\Data\Posts;

use App\Enums\PostStatus;
use Carbon\CarbonImmutable;

final readonly class PostData
{
    /**
     * @param  list<int>  $categoryIds  blog categories, in order
     * @param  list<string>  $tags  tag names as typed
     */
    public function __construct(
        public string $title,
        public string $slug,
        public ?string $excerpt,
        /** Sanitized rich text (RichText::sanitize). */
        public ?string $content,
        public PostStatus $status,
        /** Null: now when publishing (or the post's earlier date). */
        public ?CarbonImmutable $publishedAt,
        /** A staff member; null: the one saving (when creating) or unchanged. */
        public ?int $authorId,
        public bool $commentsEnabled,
        public array $categoryIds,
        public ?int $primaryCategoryId,
        public array $tags,
        public ?string $metaTitle,
        public ?string $metaDescription,
        public ?string $focusKeyphrase,
        public ?int $imageId = null,
        public bool $robotsNoindex = false,
        public ?string $canonicalUrl = null,
        public ?string $ogTitle = null,
        public ?string $ogDescription = null,
        public ?int $ogImageId = null,
    ) {}
}

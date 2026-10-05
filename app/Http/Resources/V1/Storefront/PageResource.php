<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Storefront;

use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A published page. The list (footer links, sitemaps) leaves out the content and image; never the
 * focus keyphrase, scores, authors or revisions.
 *
 * @mixin Page
 */
class PageResource extends JsonResource
{
    /** Whether to include the content (the page itself rather than the list). */
    public bool $detailed = false;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'robots_noindex' => $this->robots_noindex,
            'published_at' => $this->published_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        if (! $this->detailed) {
            return $data;
        }

        $image = $this->imageAsset()?->picture();
        $ogImage = $this->ogImageAsset()?->picture();

        return [
            ...$data,
            'content' => $this->content,
            'image' => $image,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'canonical_url' => $this->canonical_url,
            'og_title' => $this->og_title,
            'og_description' => $this->og_description,
            'og_image' => $ogImage,
            // Ready to use, with the fallbacks applied (the fields above are as the admin typed them).
            'seo' => [
                'title' => $this->meta_title ?? $this->title,
                'description' => $this->meta_description ?? $this->excerpt,
                'canonical_url' => $this->canonical_url,
                'robots_noindex' => $this->robots_noindex,
                'og' => [
                    'title' => $this->og_title ?? $this->meta_title ?? $this->title,
                    'description' => $this->og_description ?? $this->meta_description ?? $this->excerpt,
                    'image' => $ogImage ?? $image,
                ],
            ],
        ];
    }

    public function detailed(): self
    {
        $this->detailed = true;

        return $this;
    }
}

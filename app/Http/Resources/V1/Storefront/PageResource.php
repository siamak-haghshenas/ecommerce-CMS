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
            'published_at' => $this->published_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        if (! $this->detailed) {
            return $data;
        }

        return [
            ...$data,
            'content' => $this->content,
            'image' => $this->imageAsset()?->picture(),
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
        ];
    }

    public function detailed(): self
    {
        $this->detailed = true;

        return $this;
    }
}

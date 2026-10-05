<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Admin;

use App\Models\PostRevision;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A saved version of a post. The list has no content; the detail (for preview and restore) has
 * every saved field. The image is null when its file is gone or in the trash.
 *
 * @mixin PostRevision
 */
class PostRevisionResource extends JsonResource
{
    /** Whether to include the saved fields (the detail view). */
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
            'saved_by' => $this->saver === null ? null : ['id' => $this->saver->id, 'name' => $this->saver->name],
            'created_at' => $this->created_at?->toIso8601String(),
        ];

        if (! $this->detailed) {
            return $data;
        }

        return [
            ...$data,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'content' => $this->content,
            'image' => $this->image?->picture(),
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'focus_keyphrase' => $this->focus_keyphrase,
            'robots_noindex' => $this->robots_noindex,
            'canonical_url' => $this->canonical_url,
            'og_title' => $this->og_title,
            'og_description' => $this->og_description,
            'og_image' => $this->ogImage?->picture(),
        ];
    }

    public function detailed(): self
    {
        $this->detailed = true;

        return $this;
    }
}

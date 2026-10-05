<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Admin;

use App\Models\PostAutosave;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A staff member's unsaved form of a post, for "Restore". Images are null when their files are
 * gone or in the trash.
 *
 * @mixin PostAutosave
 */
class PostAutosaveResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'title' => $this->title,
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
            'saved_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

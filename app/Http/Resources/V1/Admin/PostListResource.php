<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Admin;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A row of the admin post list (no content).
 *
 * @mixin Post
 */
class PostListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'status' => $this->status->value,
            'state' => $this->state(),
            'published_at' => $this->published_at?->toIso8601String(),
            'image' => $this->imageAsset()?->picture(),
            'author' => $this->author === null ? null : ['id' => $this->author->id, 'name' => $this->author->name, 'author_name' => $this->author->author_name],
            'primary_category' => $this->primaryCategory() === null ? null : ['id' => $this->primaryCategory()->id, 'name' => $this->primaryCategory()->name],
            'robots_noindex' => $this->robots_noindex,
            'seo_score' => $this->seo_score,
            'readability_score' => $this->readability_score,
            'updated_by' => $this->editor === null ? null : ['id' => $this->editor->id, 'name' => $this->editor->name],
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Admin;

use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A page for the admin editor.
 *
 * @mixin Page
 */
class PageResource extends JsonResource
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
            'excerpt' => $this->excerpt,
            'content' => $this->content,
            'status' => $this->status->value,
            'state' => $this->state(),
            'published_at' => $this->published_at?->toIso8601String(),
            'image' => $this->imageAsset()?->picture(),
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'focus_keyphrase' => $this->focus_keyphrase,
            'seo_score' => $this->seo_score,
            'readability_score' => $this->readability_score,
            'created_by' => $this->creator === null ? null : ['id' => $this->creator->id, 'name' => $this->creator->name],
            'updated_by' => $this->editor === null ? null : ['id' => $this->editor->id, 'name' => $this->editor->name],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }

    /**
     * The relations this resource reads.
     *
     * @return list<string>
     */
    public static function relations(): array
    {
        return ['imageAssets.media', 'creator', 'editor'];
    }
}

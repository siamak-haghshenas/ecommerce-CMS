<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Admin;

use App\Data\Categories\CategoryNode;
use App\Models\Category;
use App\Support\CategoryTree;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Category
 */
class CategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $tree = CategoryTree::current();

        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'focus_keyphrase' => $this->focus_keyphrase,
            'seo_score' => $this->seo_score,
            'readability_score' => $this->readability_score,
            'position' => $this->position,
            'is_active' => $this->is_active,
            'is_visible' => $tree->isVisible($this->id),
            'image' => $this->imageUrls(),
            'ancestors' => array_map(fn (CategoryNode $node): array => [
                'id' => $node->id,
                'name' => $node->name,
                'slug' => $node->slug,
            ], $tree->ancestors($this->id)),
            'children_count' => count($tree->children($this->id)),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

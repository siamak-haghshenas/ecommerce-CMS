<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Admin;

use App\Data\Categories\CategoryNode;
use App\Support\CategoryTree;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The whole category tree for the admin panel, hidden categories included.
 */
class CategoryTreeResource extends JsonResource
{
    public function __construct(private readonly CategoryTree $tree)
    {
        parent::__construct($tree);
    }

    /**
     * Transform the resource into an array.
     *
     * @return list<array<string, mixed>>
     */
    public function toArray(Request $request): array
    {
        return $this->branch(null, true);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function branch(?int $parentId, bool $parentIsVisible): array
    {
        return array_map(function (CategoryNode $node) use ($parentIsVisible): array {
            $isVisible = $parentIsVisible && $node->isActive;

            return [
                'id' => $node->id,
                'parent_id' => $node->parentId,
                'name' => $node->name,
                'slug' => $node->slug,
                'position' => $node->position,
                'is_active' => $node->isActive,
                // False when the category or one of its ancestors is hidden.
                'is_visible' => $isVisible,
                'image' => $node->image,
                // For the live duplicate-keyphrase check and the SEO dots.
                'focus_keyphrase' => $node->focusKeyphrase,
                'seo_score' => $node->seoScore,
                'readability_score' => $node->readabilityScore,
                'children' => $this->branch($node->id, $isVisible),
            ];
        }, $this->tree->children($parentId));
    }
}

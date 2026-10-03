<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Storefront;

use App\Data\Categories\CategoryNode;
use App\Support\CategoryTree;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The visible category tree. A hidden category hides its whole branch.
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
        return $this->branch(null);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function branch(?int $parentId): array
    {
        $visibleChildren = array_filter($this->tree->children($parentId), fn (CategoryNode $node): bool => $node->isActive);

        return array_values(array_map(fn (CategoryNode $node): array => [
            'id' => $node->id,
            'name' => $node->name,
            'slug' => $node->slug,
            'image' => $node->image,
            'children' => $this->branch($node->id),
        ], $visibleChildren));
    }
}

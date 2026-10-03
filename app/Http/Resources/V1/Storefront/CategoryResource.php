<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Storefront;

use App\Data\Categories\CategoryNode;
use App\Models\Category;
use App\Support\CategoryTree;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One visible category with what a storefront needs to render its page:
 * SEO fields, breadcrumb and visible sub-categories.
 *
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
        $summary = fn (CategoryNode $node): array => ['id' => $node->id, 'name' => $node->name, 'slug' => $node->slug];

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'image' => $this->imageUrls(),
            'breadcrumb' => array_map($summary, $tree->ancestors($this->id)),
            'children' => array_values(array_map(
                fn (CategoryNode $node): array => [...$summary($node), 'image' => $node->image],
                array_filter($tree->children($this->id), fn (CategoryNode $node): bool => $node->isActive),
            )),
        ];
    }
}

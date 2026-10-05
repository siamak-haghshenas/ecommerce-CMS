<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Admin;

use App\Models\MenuItem;
use App\Support\CategoryTree;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A menu item for the menu editor: its target, whether storefronts show it (and why not), and its
 * sub-items.
 *
 * @mixin MenuItem
 */
class MenuItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $hiddenReason = $this->hiddenReason(CategoryTree::current());

        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'label' => $this->label,
            'display_label' => $this->displayLabel(),
            'page' => $this->page === null ? null : ['id' => $this->page->id, 'title' => $this->page->title, 'slug' => $this->page->slug],
            'category' => $this->category === null ? null : ['id' => $this->category->id, 'name' => $this->category->name, 'slug' => $this->category->slug],
            'url' => $this->url,
            'open_in_new_tab' => $this->open_in_new_tab,
            'is_visible' => $hiddenReason === null,
            'hidden_reason' => $hiddenReason,
            'children' => $this->parent_id === null && $this->relationLoaded('children')
                ? self::collection($this->children)->resolve($request)
                : [],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Storefront;

use App\Enums\MenuItemType;
use App\Models\MenuItem;
use App\Support\CategoryTree;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A visible menu item: what it links to and its visible sub-items. Storefronts build the address
 * of a page or category from its slug.
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
        return [
            'label' => $this->displayLabel(),
            'type' => $this->type->value,
            'url' => $this->type === MenuItemType::Url ? $this->url : null,
            'page' => $this->type === MenuItemType::Page && $this->page !== null ? ['slug' => $this->page->slug, 'title' => $this->page->title] : null,
            'category' => $this->type === MenuItemType::Category && $this->category !== null ? ['slug' => $this->category->slug, 'name' => $this->category->name, 'type' => $this->category->type->value] : null,
            'open_in_new_tab' => $this->open_in_new_tab,
            'children' => $this->parent_id === null && $this->relationLoaded('children')
                ? self::collection(self::visible($this->children))->resolve($request)
                : [],
        ];
    }

    /**
     * The items storefronts show (a hidden top item takes its sub-items with it).
     *
     * @param  iterable<MenuItem>  $items
     * @return list<MenuItem>
     */
    public static function visible(iterable $items): array
    {
        $categories = CategoryTree::current();
        $visible = [];

        foreach ($items as $item) {
            if ($item->hiddenReason($categories) === null) {
                $visible[] = $item;
            }
        }

        return $visible;
    }
}

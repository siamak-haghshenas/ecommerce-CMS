<?php

declare(strict_types=1);

namespace App\Data\Menus;

use App\Enums\MenuItemType;

final readonly class MenuItemData
{
    /**
     * @param  list<MenuItemData>  $children  sub-items (top items only)
     */
    public function __construct(
        /** An existing item of this menu, or null for a new one. */
        public ?int $id,
        public MenuItemType $type,
        public ?string $label,
        public ?int $pageId,
        public ?int $categoryId,
        public ?string $url,
        public bool $openInNewTab,
        public array $children = [],
    ) {}
}

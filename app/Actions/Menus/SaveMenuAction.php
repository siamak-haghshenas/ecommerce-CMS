<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Data\Menus\MenuItemData;
use App\Enums\MenuLocation;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class SaveMenuAction
{
    /**
     * Make the menu exactly these items, in this order: existing items are updated by id, new ones
     * created, and items left out deleted (with their sub-items).
     *
     * @param  list<MenuItemData>  $items
     */
    public function handle(User $actor, MenuLocation $location, array $items): void
    {
        DB::transaction(function () use ($actor, $location, $items): void {
            $before = $this->snapshot($location);
            $existing = MenuItem::query()->where('location', $location)->get()->keyBy('id');
            $kept = [];

            foreach ($items as $position => $item) {
                $parent = $this->saveItem($location, $existing->get((int) $item->id), $item, null, $position);
                $kept[] = $parent->id;

                foreach ($item->children as $childPosition => $child) {
                    $kept[] = $this->saveItem($location, $existing->get((int) $child->id), $child, $parent->id, $childPosition)->id;
                }
            }

            MenuItem::query()->where('location', $location)->whereNotIn('id', $kept)->delete();

            if ($this->snapshot($location) !== $before) {
                activity('admin')
                    ->causedBy($actor)
                    ->event('menu.updated')
                    ->withProperties(['location' => $location->value])
                    ->log('menu.updated');
            }
        });
    }

    private function saveItem(MenuLocation $location, ?MenuItem $item, MenuItemData $data, ?int $parentId, int $position): MenuItem
    {
        $item ??= new MenuItem(['location' => $location]);

        $item->fill([
            'parent_id' => $parentId,
            'position' => $position,
            'type' => $data->type,
            'page_id' => $data->pageId,
            'category_id' => $data->categoryId,
            'url' => $data->url,
            'label' => $data->label,
            'open_in_new_tab' => $data->openInNewTab,
        ])->save();

        return $item;
    }

    /**
     * The menu as plain rows, to tell whether a save changed anything.
     *
     * @return list<array<string, mixed>>
     */
    private function snapshot(MenuLocation $location): array
    {
        return MenuItem::query()
            ->where('location', $location)
            ->orderBy('id')
            ->get(['id', 'parent_id', 'position', 'type', 'page_id', 'category_id', 'url', 'label', 'open_in_new_tab'])
            ->map(fn (MenuItem $item): array => $item->attributesToArray())
            ->values()
            ->all();
    }
}

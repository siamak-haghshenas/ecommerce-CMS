<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Exceptions\CatalogException;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Saves a new manual order (`position`) for a whole list (attributes, options).
 */
final class ReorderListAction
{
    /**
     * The ids must be exactly the current items, so an admin working from an outdated list
     * can't silently misplace items added in the meantime.
     *
     * @param  class-string<Model>  $modelClass
     * @param  list<int>  $ids
     *
     * @throws CatalogException
     */
    public function handle(User $actor, string $modelClass, array $ids, string $event): void
    {
        DB::transaction(function () use ($actor, $modelClass, $ids, $event): void {
            $items = $modelClass::query()->lockForUpdate()->get()->keyBy('id');

            $current = $items->keys()->all();
            $sent = $ids;
            sort($current);
            sort($sent);

            if ($current !== $sent) {
                throw CatalogException::listChanged();
            }

            $changed = false;

            foreach ($ids as $position => $id) {
                $item = $items[$id];
                $item->setAttribute('position', $position);

                if ($item->isDirty()) {
                    $item->save();
                    $changed = true;
                }
            }

            if ($changed) {
                activity('admin')->causedBy($actor)->event($event)->log($event);
            }
        });
    }
}

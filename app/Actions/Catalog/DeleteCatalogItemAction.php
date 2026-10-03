<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\Attribute;
use App\Models\Option;
use App\Models\User;

final class DeleteCatalogItemAction
{
    /**
     * Delete an attribute or option with its values (and an attribute's category assignments).
     * Products don't exist yet; phase 2 refuses this while products use the item.
     */
    public function handle(User $actor, Attribute|Option $item): void
    {
        $event = $item instanceof Attribute ? 'attribute.deleted' : 'option.deleted';

        activity('admin')
            ->causedBy($actor)
            ->performedOn($item)
            ->event($event)
            ->withProperties(['name' => $item->name])
            ->log($event);

        $item->delete();
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Exceptions\CatalogException;
use App\Models\Attribute;
use App\Models\Option;
use App\Models\ProductAttributeValue;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class DeleteCatalogItemAction
{
    /**
     * Delete an attribute or option with its values (and an attribute's category assignments).
     * Refused while products use it (deleted products too: orders keep referring to them).
     *
     * @throws CatalogException
     */
    public function handle(User $actor, Attribute|Option $item): void
    {
        $inUse = $item instanceof Attribute
            ? ProductAttributeValue::query()->where('attribute_id', $item->id)->exists()
            : DB::table('product_options')->where('option_id', $item->id)->exists();

        if ($inUse) {
            throw CatalogException::inUse();
        }

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

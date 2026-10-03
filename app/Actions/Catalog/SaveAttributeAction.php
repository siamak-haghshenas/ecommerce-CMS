<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Data\Catalog\AttributeData;
use App\Models\Attribute;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class SaveAttributeAction
{
    public function __construct(private SyncValuesAction $syncValues) {}

    /**
     * Create an attribute (at the end of the list) or update one, with its values.
     * Changing the type away from "select" deletes its values.
     */
    public function handle(User $actor, ?Attribute $attribute, AttributeData $data): Attribute
    {
        return DB::transaction(function () use ($actor, $attribute, $data): Attribute {
            $isNew = $attribute === null;
            $attribute ??= new Attribute(['position' => (int) Attribute::query()->max('position') + 1]);

            $attribute->fill([
                'name' => $data->name,
                'slug' => $data->slug,
                'type' => $data->type,
                'unit' => $data->unit,
                'is_filterable' => $data->isFilterable,
            ]);

            $changedFields = array_keys($attribute->getDirty());
            $attribute->save();

            $values = $data->type->hasValues() ? $data->values : [];

            if ($this->syncValues->handle($attribute->values(), $values)) {
                $changedFields[] = 'values';
            }

            if ($isNew || $changedFields !== []) {
                $event = $isNew ? 'attribute.created' : 'attribute.updated';

                activity('admin')
                    ->causedBy($actor)
                    ->performedOn($attribute)
                    ->event($event)
                    ->withProperties($isNew ? ['name' => $attribute->name] : ['name' => $attribute->name, 'fields' => $changedFields])
                    ->log($event);
            }

            return $attribute->load('values')->loadCount('categories');
        });
    }
}

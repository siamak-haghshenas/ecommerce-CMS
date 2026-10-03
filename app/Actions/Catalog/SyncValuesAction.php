<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Data\Catalog\ValueData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Makes the values of an attribute or option match the form: existing values (by id) are
 * updated, new ones created, missing ones deleted; the list order becomes `position`.
 */
final class SyncValuesAction
{
    /**
     * Call inside a transaction. The ids were validated to belong to the parent.
     *
     * @param  HasMany<covariant Model, covariant Model>  $relation
     * @param  list<ValueData>  $values
     * @return bool whether anything changed
     */
    public function handle(HasMany $relation, array $values, bool $withColor = false): bool
    {
        $existing = $relation->getQuery()->clone()->get()->keyBy('id');

        $wanted = [];
        foreach ($values as $position => $value) {
            $wanted[] = [$value->id, [
                'value' => $value->value,
                'slug' => $value->slug,
                'position' => $position,
                ...($withColor ? ['color_hex' => $value->colorHex] : []),
            ]];
        }

        $keptIds = array_values(array_filter(array_column($wanted, 0)));
        $removed = $existing->except($keptIds);
        $updated = array_filter($wanted, function (array $item) use ($existing): bool {
            [$id, $attributes] = $item;

            return $id !== null && $existing[$id]->fill($attributes)->isDirty();
        });
        $created = array_filter($wanted, fn (array $item): bool => $item[0] === null);

        foreach ($removed as $value) {
            $value->delete();
        }

        // Values whose slug changes first get a temporary slug, so two values can swap slugs
        // without hitting the "slug unique per parent" index halfway through. Their slug stays
        // dirty on the model, so save() below writes the real one.
        foreach ($updated as [$id]) {
            if ($existing[$id]->isDirty('slug')) {
                $existing[$id]->newQuery()->whereKey($id)->update(['slug' => "__{$id}"]);
            }
        }

        foreach ($updated as [$id]) {
            $existing[$id]->save();
        }

        foreach ($created as [, $attributes]) {
            $relation->create($attributes);
        }

        return $removed->isNotEmpty() || $updated !== [] || $created !== [];
    }
}

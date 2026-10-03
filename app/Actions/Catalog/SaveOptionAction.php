<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Data\Catalog\OptionData;
use App\Models\Option;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class SaveOptionAction
{
    public function __construct(private SyncValuesAction $syncValues) {}

    /**
     * Create an option (at the end of the list) or update one, with its values.
     */
    public function handle(User $actor, ?Option $option, OptionData $data): Option
    {
        return DB::transaction(function () use ($actor, $option, $data): Option {
            $isNew = $option === null;
            $option ??= new Option(['position' => (int) Option::query()->max('position') + 1]);

            $option->fill([
                'name' => $data->name,
                'slug' => $data->slug,
                'display_type' => $data->displayType,
            ]);

            $changedFields = array_keys($option->getDirty());
            $option->save();

            if ($this->syncValues->handle($option->values(), $data->values, withColor: true)) {
                $changedFields[] = 'values';
            }

            if ($isNew || $changedFields !== []) {
                $event = $isNew ? 'option.created' : 'option.updated';

                activity('admin')
                    ->causedBy($actor)
                    ->performedOn($option)
                    ->event($event)
                    ->withProperties($isNew ? ['name' => $option->name] : ['name' => $option->name, 'fields' => $changedFields])
                    ->log($event);
            }

            return $option->load('values');
        });
    }
}

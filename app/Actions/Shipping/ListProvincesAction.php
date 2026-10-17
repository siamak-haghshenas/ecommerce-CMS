<?php

declare(strict_types=1);

namespace App\Actions\Shipping;

use App\Models\Province;
use Illuminate\Database\Eloquent\Collection;

final class ListProvincesAction
{
    /**
     * Every province with its cities (address forms, courier city pickers).
     *
     * @return Collection<int, Province>
     */
    public function handle(): Collection
    {
        return Province::query()->orderBy('position')->orderBy('id')->with('cities')->get();
    }
}

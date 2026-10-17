<?php

declare(strict_types=1);

namespace App\Actions\Shipping;

use App\Models\ShippingMethod;
use Illuminate\Database\Eloquent\Collection;

final class ListShippingMethodsAction
{
    /**
     * Every method in the order customers see them, with ranges and cities. Not paginated: the
     * order is managed by hand.
     *
     * @return Collection<int, ShippingMethod>
     */
    public function handle(): Collection
    {
        return ShippingMethod::query()->ordered()->with(['rates', 'cities.province'])->get();
    }
}

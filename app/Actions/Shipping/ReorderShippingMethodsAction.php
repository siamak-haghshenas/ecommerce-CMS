<?php

declare(strict_types=1);

namespace App\Actions\Shipping;

use App\Exceptions\ShippingException;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ReorderShippingMethodsAction
{
    /**
     * Save the order customers see the methods in. The ids must be exactly the current methods,
     * so an admin working from an outdated list can't silently misplace methods added meanwhile.
     *
     * @param  list<int>  $ids
     *
     * @throws ShippingException
     */
    public function handle(User $actor, array $ids): void
    {
        DB::transaction(function () use ($actor, $ids): void {
            $methods = ShippingMethod::query()->lockForUpdate()->get()->keyBy('id');

            $current = $methods->keys()->all();
            sort($current);
            $sent = $ids;
            sort($sent);

            if ($current !== $sent) {
                throw ShippingException::listChanged();
            }

            $changed = false;

            foreach ($ids as $position => $id) {
                /** @var ShippingMethod $method */
                $method = $methods->get($id);
                $method->position = $position;

                if ($method->isDirty()) {
                    $method->save();
                    $changed = true;
                }
            }

            if ($changed) {
                activity('admin')->causedBy($actor)->event('shipping_method.reordered')->log('shipping_method.reordered');
            }
        });
    }
}

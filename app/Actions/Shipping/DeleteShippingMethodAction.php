<?php

declare(strict_types=1);

namespace App\Actions\Shipping;

use App\Models\ShippingMethod;
use App\Models\User;

final class DeleteShippingMethodAction
{
    /**
     * Delete a shipping method (a soft delete: orders will keep pointing at it, with its name and
     * fee copied). Customers no longer see it.
     */
    public function handle(User $actor, ShippingMethod $method): void
    {
        $method->delete();

        activity('admin')
            ->causedBy($actor)
            ->event('shipping_method.deleted')
            ->withProperties(['name' => $method->name])
            ->log('shipping_method.deleted');
    }
}

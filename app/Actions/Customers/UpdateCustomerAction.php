<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\Data\Admin\UpdateCustomerData;
use App\Exceptions\AccountActionException;
use App\Models\User;

final class UpdateCustomerAction
{
    /**
     * Update a customer's profile. The phone number is not editable here: changing it
     * requires verifying the new number, which only the customer can do.
     *
     * @throws AccountActionException
     */
    public function handle(User $actor, User $customer, UpdateCustomerData $data): User
    {
        if ($customer->isAnonymised()) {
            throw AccountActionException::customerAnonymised();
        }

        $customer->fill([
            'name' => $data->name,
            'email' => $data->email,
            'locale' => $data->locale,
        ]);

        $changedFields = array_keys($customer->getDirty());

        if ($changedFields !== []) {
            $customer->save();

            // Only field names are logged, never values, so the log holds no personal data.
            activity('admin')
                ->causedBy($actor)
                ->performedOn($customer)
                ->event('customer.updated')
                ->withProperties(['fields' => $changedFields])
                ->log('customer.updated');
        }

        return $customer;
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\Actions\Auth\EndUserSessionsAction;
use App\Exceptions\AccountActionException;
use App\Models\User;

final readonly class SetCustomerStatusAction
{
    public function __construct(private EndUserSessionsAction $endSessions) {}

    /**
     * Activate or deactivate a customer. Deactivating keeps all data but logs the
     * customer out everywhere and blocks further logins.
     *
     * @throws AccountActionException
     */
    public function handle(User $actor, User $customer, bool $active): User
    {
        if ($customer->isAnonymised()) {
            throw AccountActionException::customerAnonymised();
        }

        if ($customer->is_active === $active) {
            return $customer;
        }

        $customer->forceFill(['is_active' => $active])->save();

        if (! $active) {
            $this->endSessions->handle($customer);
        }

        $event = $active ? 'customer.activated' : 'customer.deactivated';

        activity('admin')->causedBy($actor)->performedOn($customer)->event($event)->log($event);

        return $customer;
    }
}

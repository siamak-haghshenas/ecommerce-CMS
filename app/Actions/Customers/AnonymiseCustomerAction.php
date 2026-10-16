<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\Actions\Auth\EndUserSessionsAction;
use App\Exceptions\AccountActionException;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class AnonymiseCustomerAction
{
    /**
     * Stored in place of the name; API resources show a translated label instead.
     */
    public const string ANONYMISED_NAME = 'Anonymised user';

    public function __construct(private EndUserSessionsAction $endSessions) {}

    /**
     * Irreversibly remove a customer's personal data for a data-deletion request.
     *
     * The account row stays (with its id) so records that must be kept, such as
     * orders, still point at it; it can never log in again.
     *
     * @throws AccountActionException
     */
    public function handle(User $actor, User $customer): User
    {
        if ($customer->isAnonymised()) {
            throw AccountActionException::customerAnonymised();
        }

        DB::transaction(function () use ($customer): void {
            if ($customer->phone_number !== null) {
                OtpCode::query()->where('phone_number', $customer->phone_number)->delete();
            }

            // Coupons limited to chosen customers would otherwise keep listing them.
            $customer->coupons()->detach();

            $customer->forceFill([
                'name' => self::ANONYMISED_NAME,
                'phone_number' => null,
                'email' => null,
                'password' => Str::random(64),
                'is_active' => false,
                'anonymised_at' => now(),
            ])->save();

            $this->endSessions->handle($customer);
        });

        activity('admin')->causedBy($actor)->performedOn($customer)->event('customer.anonymised')->log('customer.anonymised');

        return $customer;
    }
}

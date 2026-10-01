<?php

declare(strict_types=1);

namespace App\Actions\Staff;

use App\Data\Admin\AddStaffMemberData;
use App\Exceptions\AccountActionException;
use App\Models\User;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AddStaffMemberAction
{
    /**
     * Give a phone number a staff role.
     *
     * An existing customer is promoted (one account for both). Otherwise a verified
     * account is created without a usable password: staff log in with an SMS code.
     *
     * @throws AccountActionException
     */
    public function handle(User $actor, AddStaffMemberData $data): User
    {
        $member = User::query()->firstWhere('phone_number', $data->phoneNumber);

        if ($member?->isStaff()) {
            throw AccountActionException::alreadyStaff();
        }

        $promoted = $member !== null;

        $member = DB::transaction(function () use ($member, $data): User {
            if ($member === null) {
                if ($data->name === null || $data->name === '') {
                    throw AccountActionException::nameRequiredForNewStaff();
                }

                $member = new User;
                $member->forceFill([
                    'name' => $data->name,
                    'phone_number' => $data->phoneNumber,
                    'password' => Str::random(64),
                    'locale' => App::getLocale(),
                ]);
            }

            // The admin vouches for the number; staff log in by SMS code to it.
            $member->forceFill(['phone_verified_at' => $member->phone_verified_at ?? now()])->save();
            $member->syncRoles($data->role->value);

            return $member;
        });

        activity('admin')
            ->causedBy($actor)
            ->performedOn($member)
            ->event('staff.added')
            ->withProperties(['role' => $data->role->value, 'promoted_customer' => $promoted])
            ->log('staff.added');

        return $member;
    }
}

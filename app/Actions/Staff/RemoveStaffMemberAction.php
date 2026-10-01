<?php

declare(strict_types=1);

namespace App\Actions\Staff;

use App\Enums\UserRole;
use App\Exceptions\AccountActionException;
use App\Models\User;

final readonly class RemoveStaffMemberAction
{
    public function __construct(private GuardStaffChangeAction $guard) {}

    /**
     * Take away staff access; the account stays and becomes a regular customer.
     *
     * @throws AccountActionException
     */
    public function handle(User $actor, User $member): User
    {
        $this->guard->handle($actor, $member, removesSuperAdmin: true);

        $previousRole = $member->primaryRole();

        $member->syncRoles(UserRole::Customer->value);

        activity('admin')
            ->causedBy($actor)
            ->performedOn($member)
            ->event('staff.removed')
            ->withProperties(['role' => $previousRole?->value])
            ->log('staff.removed');

        return $member;
    }
}

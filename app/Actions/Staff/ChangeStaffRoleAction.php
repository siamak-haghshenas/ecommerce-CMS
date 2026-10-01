<?php

declare(strict_types=1);

namespace App\Actions\Staff;

use App\Enums\UserRole;
use App\Exceptions\AccountActionException;
use App\Models\User;

final readonly class ChangeStaffRoleAction
{
    public function __construct(private GuardStaffChangeAction $guard) {}

    /**
     * @throws AccountActionException
     */
    public function handle(User $actor, User $member, UserRole $role): User
    {
        $currentRole = $member->primaryRole();

        $this->guard->handle($actor, $member, removesSuperAdmin: $role !== UserRole::SuperAdmin);

        if ($currentRole === $role) {
            return $member;
        }

        $member->syncRoles($role->value);

        activity('admin')
            ->causedBy($actor)
            ->performedOn($member)
            ->event('staff.role_changed')
            ->withProperties(['from' => $currentRole?->value, 'to' => $role->value])
            ->log('staff.role_changed');

        return $member;
    }
}

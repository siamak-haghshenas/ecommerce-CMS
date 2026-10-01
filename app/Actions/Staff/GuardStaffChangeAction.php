<?php

declare(strict_types=1);

namespace App\Actions\Staff;

use App\Enums\UserRole;
use App\Exceptions\AccountActionException;
use App\Models\User;

final class GuardStaffChangeAction
{
    /**
     * Refuse staff changes that would lock the store out of its own admin panel.
     *
     * @param  bool  $removesSuperAdmin  whether the change takes the member out of the active super admins
     *
     * @throws AccountActionException
     */
    public function handle(User $actor, User $member, bool $removesSuperAdmin): void
    {
        if ($actor->is($member)) {
            throw AccountActionException::cannotChangeOwnAccount();
        }

        $isActiveSuperAdmin = $member->is_active && $member->hasRole(UserRole::SuperAdmin->value);

        if ($removesSuperAdmin && $isActiveSuperAdmin && $this->activeSuperAdminCount() <= 1) {
            throw AccountActionException::lastSuperAdmin();
        }
    }

    private function activeSuperAdminCount(): int
    {
        return User::query()->role(UserRole::SuperAdmin->value)->where('is_active', true)->count();
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Staff;

use App\Actions\Auth\EndUserSessionsAction;
use App\Exceptions\AccountActionException;
use App\Models\User;

final readonly class SetStaffStatusAction
{
    public function __construct(
        private GuardStaffChangeAction $guard,
        private EndUserSessionsAction $endSessions,
    ) {}

    /**
     * Activate or deactivate a staff member; deactivating logs them out everywhere.
     *
     * @throws AccountActionException
     */
    public function handle(User $actor, User $member, bool $active): User
    {
        $this->guard->handle($actor, $member, removesSuperAdmin: ! $active);

        if ($member->is_active === $active) {
            return $member;
        }

        $member->forceFill(['is_active' => $active])->save();

        if (! $active) {
            $this->endSessions->handle($member);
        }

        $event = $active ? 'staff.activated' : 'staff.deactivated';

        activity('admin')->causedBy($actor)->performedOn($member)->event($event)->log($event);

        return $member;
    }
}

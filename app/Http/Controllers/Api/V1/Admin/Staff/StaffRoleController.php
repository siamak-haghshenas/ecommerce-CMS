<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Staff;

use App\Actions\Staff\ChangeStaffRoleAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Staff\UpdateStaffRoleRequest;
use App\Http\Resources\V1\Admin\StaffMemberResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;

class StaffRoleController extends Controller
{
    public function __invoke(
        UpdateStaffRoleRequest $request,
        User $staffMember,
        ChangeStaffRoleAction $changeRole,
        #[CurrentUser] User $actor,
    ): StaffMemberResource {
        return new StaffMemberResource($changeRole->handle($actor, $staffMember, $request->role()));
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Staff;

use App\Actions\Staff\SetStaffStatusAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UpdateAccountStatusRequest;
use App\Http\Resources\V1\Admin\StaffMemberResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;

class StaffStatusController extends Controller
{
    public function __invoke(
        UpdateAccountStatusRequest $request,
        User $staffMember,
        SetStaffStatusAction $setStatus,
        #[CurrentUser] User $actor,
    ): StaffMemberResource {
        return new StaffMemberResource($setStatus->handle($actor, $staffMember, $request->isActive()));
    }
}

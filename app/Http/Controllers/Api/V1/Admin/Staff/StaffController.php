<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Staff;

use App\Actions\Staff\AddStaffMemberAction;
use App\Actions\Staff\RemoveStaffMemberAction;
use App\Actions\Staff\SearchStaffAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Staff\IndexStaffRequest;
use App\Http\Requests\Api\V1\Admin\Staff\StoreStaffRequest;
use App\Http\Resources\V1\Admin\StaffMemberResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class StaffController extends Controller
{
    public function index(IndexStaffRequest $request, SearchStaffAction $search): AnonymousResourceCollection
    {
        return StaffMemberResource::collection($search->handle($request->toFilters()));
    }

    public function store(StoreStaffRequest $request, AddStaffMemberAction $add, #[CurrentUser] User $actor): JsonResponse
    {
        return (new StaffMemberResource($add->handle($actor, $request->toData())))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Remove staff access; the account becomes a customer.
     */
    public function destroy(User $staffMember, RemoveStaffMemberAction $remove, #[CurrentUser] User $actor): Response
    {
        $remove->handle($actor, $staffMember);

        return response()->noContent();
    }
}

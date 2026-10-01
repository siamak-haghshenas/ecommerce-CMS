<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Customers;

use App\Actions\Customers\SetCustomerStatusAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UpdateAccountStatusRequest;
use App\Http\Resources\V1\Admin\CustomerResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;

class CustomerStatusController extends Controller
{
    public function __invoke(
        UpdateAccountStatusRequest $request,
        User $customer,
        SetCustomerStatusAction $setStatus,
        #[CurrentUser] User $actor,
    ): CustomerResource {
        return new CustomerResource($setStatus->handle($actor, $customer, $request->isActive()));
    }
}

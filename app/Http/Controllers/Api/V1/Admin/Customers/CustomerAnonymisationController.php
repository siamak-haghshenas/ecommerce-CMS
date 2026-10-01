<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Customers;

use App\Actions\Customers\AnonymiseCustomerAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Admin\CustomerResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;

class CustomerAnonymisationController extends Controller
{
    public function __invoke(User $customer, AnonymiseCustomerAction $anonymise, #[CurrentUser] User $actor): CustomerResource
    {
        return new CustomerResource($anonymise->handle($actor, $customer));
    }
}

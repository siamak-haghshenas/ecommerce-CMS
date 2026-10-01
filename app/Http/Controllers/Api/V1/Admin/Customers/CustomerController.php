<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Customers;

use App\Actions\Customers\SearchCustomersAction;
use App\Actions\Customers\UpdateCustomerAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Customers\IndexCustomersRequest;
use App\Http\Requests\Api\V1\Admin\Customers\UpdateCustomerRequest;
use App\Http\Resources\V1\Admin\CustomerResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerController extends Controller
{
    public function index(IndexCustomersRequest $request, SearchCustomersAction $search): AnonymousResourceCollection
    {
        return CustomerResource::collection($search->handle($request->toFilters()));
    }

    public function show(User $customer): CustomerResource
    {
        return new CustomerResource($customer);
    }

    public function update(
        UpdateCustomerRequest $request,
        User $customer,
        UpdateCustomerAction $update,
        #[CurrentUser] User $actor,
    ): CustomerResource {
        return new CustomerResource($update->handle($actor, $customer, $request->toData()));
    }
}

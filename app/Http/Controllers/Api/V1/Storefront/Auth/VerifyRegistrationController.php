<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Storefront\Auth;

use App\Actions\Auth\IssueCustomerTokenAction;
use App\Actions\Auth\VerifyRegistrationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Storefront\Auth\VerifyOtpRequest;
use App\Http\Resources\V1\Storefront\CustomerTokenResource;

class VerifyRegistrationController extends Controller
{
    public function __invoke(
        VerifyOtpRequest $request,
        VerifyRegistrationAction $verifyRegistration,
        IssueCustomerTokenAction $issueToken,
    ): CustomerTokenResource {
        $user = $verifyRegistration->handle($request->phoneNumber(), $request->code());

        return new CustomerTokenResource($user, $issueToken->handle($user, $request->deviceName()));
    }
}

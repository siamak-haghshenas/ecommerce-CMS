<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Storefront\Auth;

use App\Actions\Auth\IssueCustomerTokenAction;
use App\Actions\Auth\LoginWithPasswordAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Storefront\Auth\PasswordLoginRequest;
use App\Http\Resources\V1\Storefront\CustomerTokenResource;

class LoginWithPasswordController extends Controller
{
    public function __invoke(
        PasswordLoginRequest $request,
        LoginWithPasswordAction $login,
        IssueCustomerTokenAction $issueToken,
    ): CustomerTokenResource {
        $user = $login->handle($request->phoneNumber(), $request->string('password')->toString());

        return new CustomerTokenResource($user, $issueToken->handle($user, $request->deviceName()));
    }
}

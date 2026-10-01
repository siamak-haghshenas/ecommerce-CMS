<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Storefront\Auth;

use App\Actions\Auth\IssueCustomerTokenAction;
use App\Actions\Auth\ResetPasswordAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Storefront\Auth\ResetPasswordRequest;
use App\Http\Resources\V1\Storefront\CustomerTokenResource;

class ResetPasswordController extends Controller
{
    /**
     * Every existing token is revoked by the reset; the response carries a fresh one.
     */
    public function __invoke(
        ResetPasswordRequest $request,
        ResetPasswordAction $resetPassword,
        IssueCustomerTokenAction $issueToken,
    ): CustomerTokenResource {
        $user = $resetPassword->handle(
            $request->phoneNumber(),
            $request->code(),
            $request->string('password')->toString(),
        );

        return new CustomerTokenResource($user, $issueToken->handle($user, $request->deviceName()));
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Storefront\Auth;

use App\Actions\Auth\ResetPasswordAction;
use App\Http\Controllers\Concerns\ManagesAuthSession;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Storefront\Auth\ResetPasswordRequest;
use App\Http\Resources\V1\Storefront\UserResource;

class ResetPasswordController extends Controller
{
    use ManagesAuthSession;

    public function __invoke(ResetPasswordRequest $request, ResetPasswordAction $resetPassword): UserResource
    {
        $user = $resetPassword->handle(
            $request->phoneNumber(),
            $request->code(),
            $request->string('password')->toString(),
        );

        $this->startSession($request, $user);

        return new UserResource($user);
    }
}

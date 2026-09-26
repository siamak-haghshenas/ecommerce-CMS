<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Auth;

use App\Actions\Auth\LoginWithOtpAction;
use App\Exceptions\AuthException;
use App\Http\Controllers\Concerns\ManagesAuthSession;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Auth\VerifyOtpRequest;
use App\Http\Resources\V1\Admin\UserResource;

class LoginWithOtpController extends Controller
{
    use ManagesAuthSession;

    public function __invoke(VerifyOtpRequest $request, LoginWithOtpAction $login): UserResource
    {
        $user = $login->handle($request->phoneNumber(), $request->code());

        if (! $user->canAccessAdminPanel()) {
            throw AuthException::notStaff();
        }

        $this->startSession($request, $user);

        return new UserResource($user);
    }
}

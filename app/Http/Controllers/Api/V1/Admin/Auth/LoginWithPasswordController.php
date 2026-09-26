<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Auth;

use App\Actions\Auth\LoginWithPasswordAction;
use App\Exceptions\AuthException;
use App\Http\Controllers\Concerns\ManagesAuthSession;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Auth\PasswordLoginRequest;
use App\Http\Resources\V1\Admin\UserResource;

class LoginWithPasswordController extends Controller
{
    use ManagesAuthSession;

    public function __invoke(PasswordLoginRequest $request, LoginWithPasswordAction $login): UserResource
    {
        $user = $login->handle($request->phoneNumber(), $request->string('password')->toString());

        if (! $user->canAccessAdminPanel()) {
            throw AuthException::notStaff();
        }

        $this->startSession($request, $user);

        return new UserResource($user);
    }
}

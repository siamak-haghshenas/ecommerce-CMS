<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Storefront\Auth;

use App\Actions\Auth\LoginWithOtpAction;
use App\Http\Controllers\Concerns\ManagesAuthSession;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Storefront\Auth\VerifyOtpRequest;
use App\Http\Resources\V1\Storefront\UserResource;

class LoginWithOtpController extends Controller
{
    use ManagesAuthSession;

    public function __invoke(VerifyOtpRequest $request, LoginWithOtpAction $login): UserResource
    {
        $user = $login->handle($request->phoneNumber(), $request->code());

        $this->startSession($request, $user);

        return new UserResource($user);
    }
}

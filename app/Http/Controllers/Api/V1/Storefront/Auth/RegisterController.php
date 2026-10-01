<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Storefront\Auth;

use App\Actions\Auth\RegisterCustomerAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Storefront\Auth\RegisterRequest;
use App\Http\Resources\V1\OtpChallengeResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class RegisterController extends Controller
{
    public function __invoke(RegisterRequest $request, RegisterCustomerAction $register): JsonResponse
    {
        $register->handle($request->toData(), $request->ip());

        return (new OtpChallengeResource($request->phoneNumber()))
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }
}

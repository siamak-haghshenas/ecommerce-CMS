<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Auth;

use App\Actions\Auth\RequestOtpAction;
use App\Enums\OtpPurpose;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Auth\PhoneNumberRequest;
use App\Http\Resources\V1\OtpChallengeResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class SendLoginOtpController extends Controller
{
    public function __invoke(PhoneNumberRequest $request, RequestOtpAction $requestOtp): JsonResponse
    {
        $requestOtp->handle($request->phoneNumber(), OtpPurpose::Login, $request->ip());

        return (new OtpChallengeResource($request->phoneNumber()))
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }
}

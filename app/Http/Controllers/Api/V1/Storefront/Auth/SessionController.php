<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Storefront\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Storefront\UserResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Sanctum\PersonalAccessToken;

class SessionController extends Controller
{
    /**
     * The customer the Bearer token belongs to.
     */
    public function show(#[CurrentUser] User $customer): UserResource
    {
        return new UserResource($customer);
    }

    /**
     * Log out: revoke only the Bearer token sent with this request (other devices stay logged in).
     */
    public function destroy(Request $request): Response
    {
        PersonalAccessToken::findToken((string) $request->bearerToken())?->delete();

        return response()->noContent();
    }
}

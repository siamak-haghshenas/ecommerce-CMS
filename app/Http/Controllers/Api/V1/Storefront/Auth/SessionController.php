<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Storefront\Auth;

use App\Http\Controllers\Concerns\ManagesAuthSession;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Storefront\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SessionController extends Controller
{
    use ManagesAuthSession;

    /**
     * The currently logged-in customer.
     */
    public function show(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    /**
     * Log out.
     */
    public function destroy(Request $request): Response
    {
        $this->endSession($request);

        return response()->noContent();
    }
}

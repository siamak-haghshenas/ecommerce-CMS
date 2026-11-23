<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * For endpoints guests use too (the cart): a request with a Bearer token is authenticated like
 * `auth:sanctum` (an invalid or expired token is refused, so the storefront knows to log in
 * again); one without is let through as a guest.
 */
class AuthenticateIfTokenSent implements AuthenticatesRequests
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     *
     * @throws AuthenticationException
     */
    public function handle(Request $request, Closure $next): Response
    {
        Auth::shouldUse('sanctum');

        if ($request->bearerToken() !== null && $request->user() === null) {
            throw new AuthenticationException;
        }

        return $next($request);
    }
}

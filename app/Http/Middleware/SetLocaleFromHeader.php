<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uses the Accept-Language header to pick one of the supported locales (fa, en).
 */
class SetLocaleFromHeader
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var list<string> $supportedLocales */
        $supportedLocales = config('app.supported_locales');

        if ($request->hasHeader('Accept-Language')) {
            App::setLocale($request->getPreferredLanguage($supportedLocales) ?? config('app.locale'));
        }

        return $next($request);
    }
}

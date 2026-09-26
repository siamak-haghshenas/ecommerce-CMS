<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Starts and ends cookie-based (Sanctum SPA) sessions.
 */
trait ManagesAuthSession
{
    protected function startSession(Request $request, User $user): void
    {
        Auth::guard('web')->login($user);

        $request->session()->regenerate();
    }

    protected function endSession(Request $request): void
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}

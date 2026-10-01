<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EndUserSessionsAction
{
    /**
     * Log the user out everywhere: API tokens, "remember me" cookies and stored sessions.
     */
    public function handle(User $user): void
    {
        $user->tokens()->delete();
        $user->forceFill(['remember_token' => Str::random(60)])->save();

        if (config('session.driver') === 'database') {
            DB::table((string) config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }
    }
}

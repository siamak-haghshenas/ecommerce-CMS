<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use Laravel\Sanctum\NewAccessToken;

final class IssueCustomerTokenAction
{
    public const string DEFAULT_DEVICE_NAME = 'storefront';

    /**
     * Log a customer in for an external storefront: a Bearer API token rather than a
     * cookie session, so it works from any domain or technology (web, SSR, mobile).
     */
    public function handle(User $user, ?string $deviceName = null): NewAccessToken
    {
        $user->forceFill(['last_login_at' => now()])->save();

        return $user->createToken(
            $deviceName ?: self::DEFAULT_DEVICE_NAME,
            ['customer'],
            now()->plus(minutes: (int) config('sanctum.expiration')),
        );
    }
}

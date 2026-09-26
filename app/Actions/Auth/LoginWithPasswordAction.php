<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Exceptions\AuthException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

final class LoginWithPasswordAction
{
    /**
     * Return the user matching the credentials. Unknown numbers and wrong passwords
     * fail identically, and account state is only revealed after a correct password.
     *
     * @throws AuthException
     */
    public function handle(string $phoneNumber, string $password): User
    {
        $user = User::query()->firstWhere('phone_number', $phoneNumber);

        if ($user === null || ! Hash::check($password, $user->password)) {
            throw AuthException::invalidCredentials();
        }

        if (! $user->hasVerifiedPhoneNumber()) {
            throw AuthException::phoneNotVerified();
        }

        if (! $user->is_active) {
            throw AuthException::accountInactive();
        }

        return $user;
    }
}

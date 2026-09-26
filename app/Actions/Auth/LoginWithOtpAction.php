<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Enums\OtpPurpose;
use App\Exceptions\AuthException;
use App\Models\User;

final readonly class LoginWithOtpAction
{
    public function __construct(private VerifyOtpAction $verifyOtp) {}

    /**
     * Return the verified user once their login code has been confirmed.
     *
     * @throws AuthException
     */
    public function handle(string $phoneNumber, string $code): User
    {
        $this->verifyOtp->handle($phoneNumber, OtpPurpose::Login, $code);

        $user = User::query()
            ->where('phone_number', $phoneNumber)
            ->whereNotNull('phone_verified_at')
            ->first() ?? throw AuthException::otpInvalid();

        if (! $user->is_active) {
            throw AuthException::accountInactive();
        }

        return $user;
    }
}

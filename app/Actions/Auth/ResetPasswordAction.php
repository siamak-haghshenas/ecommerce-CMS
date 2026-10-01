<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Enums\OtpPurpose;
use App\Exceptions\AuthException;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;

final readonly class ResetPasswordAction
{
    public function __construct(
        private VerifyOtpAction $verifyOtp,
        private EndUserSessionsAction $endSessions,
    ) {}

    /**
     * Set a new password after confirming the reset code, ending every existing session.
     *
     * @throws AuthException
     */
    public function handle(string $phoneNumber, string $code, string $password): User
    {
        $this->verifyOtp->handle($phoneNumber, OtpPurpose::PasswordReset, $code);

        $user = User::query()
            ->where('phone_number', $phoneNumber)
            ->whereNotNull('phone_verified_at')
            ->first() ?? throw AuthException::otpInvalid();

        $user->forceFill(['password' => $password])->save();

        $this->endSessions->handle($user);

        event(new PasswordReset($user));

        if (! $user->is_active) {
            throw AuthException::accountInactive();
        }

        return $user;
    }
}

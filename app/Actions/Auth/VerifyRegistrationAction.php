<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Enums\OtpPurpose;
use App\Exceptions\AuthException;
use App\Models\User;
use Illuminate\Auth\Events\Registered;

final readonly class VerifyRegistrationAction
{
    public function __construct(private VerifyOtpAction $verifyOtp) {}

    /**
     * Verify the phone number of an unfinished registration, activating the account.
     *
     * @throws AuthException
     */
    public function handle(string $phoneNumber, string $code): User
    {
        $this->verifyOtp->handle($phoneNumber, OtpPurpose::Register, $code);

        $user = User::query()
            ->where('phone_number', $phoneNumber)
            ->whereNull('phone_verified_at')
            ->first() ?? throw AuthException::otpInvalid();

        $user->forceFill(['phone_verified_at' => now()])->save();

        event(new Registered($user));

        return $user;
    }
}

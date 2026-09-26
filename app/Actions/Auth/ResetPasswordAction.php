<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Enums\OtpPurpose;
use App\Exceptions\AuthException;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class ResetPasswordAction
{
    public function __construct(private VerifyOtpAction $verifyOtp) {}

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

        $user->forceFill([
            'password' => $password,
            'remember_token' => Str::random(60),
        ])->save();

        $this->endExistingSessions($user);

        event(new PasswordReset($user));

        if (! $user->is_active) {
            throw AuthException::accountInactive();
        }

        return $user;
    }

    private function endExistingSessions(User $user): void
    {
        $user->tokens()->delete();

        if (config('session.driver') === 'database') {
            DB::table((string) config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }
    }
}

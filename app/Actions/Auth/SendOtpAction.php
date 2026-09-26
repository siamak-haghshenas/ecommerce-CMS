<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Contracts\SmsSender;
use App\Enums\OtpPurpose;
use App\Exceptions\AuthException;
use App\Models\OtpCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

final readonly class SendOtpAction
{
    public function __construct(private SmsSender $sms) {}

    /**
     * Issue a new code (cancelling earlier ones for the same purpose) and send it by SMS.
     *
     * When $deliver is false only the resend cooldown is applied, so a request for
     * an unknown phone number is indistinguishable from a real one.
     *
     * @throws AuthException when a code was requested too recently.
     */
    public function handle(string $phoneNumber, OtpPurpose $purpose, bool $deliver = true, ?string $ipAddress = null): void
    {
        $cooldownKey = "otp-cooldown:{$purpose->value}:{$phoneNumber}";

        if (RateLimiter::tooManyAttempts($cooldownKey, maxAttempts: 1)) {
            throw AuthException::otpResendCooldown(RateLimiter::availableIn($cooldownKey));
        }

        if ($deliver) {
            $code = $this->generateCode();

            DB::transaction(function () use ($phoneNumber, $purpose, $code, $ipAddress): void {
                OtpCode::query()->unconsumed($phoneNumber, $purpose)->update(['consumed_at' => now()]);

                OtpCode::query()->create([
                    'phone_number' => $phoneNumber,
                    'purpose' => $purpose,
                    'code_hash' => Hash::make($code),
                    'expires_at' => now()->plus(seconds: (int) config('otp.ttl_seconds')),
                    'ip_address' => $ipAddress,
                ]);
            });

            $this->sms->sendOtp($phoneNumber, $code);
        }

        RateLimiter::hit($cooldownKey, (int) config('otp.resend_cooldown_seconds'));
    }

    private function generateCode(): string
    {
        $length = (int) config('otp.length');

        return str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Enums\OtpPurpose;
use App\Exceptions\AuthException;
use App\Models\OtpCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class VerifyOtpAction
{
    /**
     * Consume the latest code for the phone number and purpose if it matches.
     *
     * @throws AuthException when the code is missing, expired, wrong or used up.
     */
    public function handle(string $phoneNumber, OtpPurpose $purpose, string $code): void
    {
        // The failure is thrown after the transaction so the attempt counter is committed.
        $failure = DB::transaction(function () use ($phoneNumber, $purpose, $code): ?AuthException {
            $otp = OtpCode::query()
                ->unconsumed($phoneNumber, $purpose)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($otp === null) {
                return AuthException::otpInvalid();
            }

            if ($otp->isExpired()) {
                return AuthException::otpExpired();
            }

            if (Hash::check($code, $otp->code_hash)) {
                $otp->forceFill(['consumed_at' => now()])->save();

                return null;
            }

            $otp->attempts++;

            if ($otp->attempts >= (int) config('otp.max_attempts')) {
                $otp->consumed_at = now();
                $otp->save();

                return AuthException::otpTooManyAttempts();
            }

            $otp->save();

            return AuthException::otpInvalid();
        });

        if ($failure !== null) {
            throw $failure;
        }
    }
}

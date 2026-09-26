<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Enums\OtpPurpose;
use App\Models\User;

/**
 * Sends a code only when the phone number belongs to an account that may use it,
 * while responding identically either way so registered numbers cannot be discovered.
 */
final readonly class RequestOtpAction
{
    public function __construct(private SendOtpAction $sendOtp) {}

    public function handle(string $phoneNumber, OtpPurpose $purpose, ?string $ipAddress = null): void
    {
        $this->sendOtp->handle(
            $phoneNumber,
            $purpose,
            deliver: $this->isEligible($phoneNumber, $purpose),
            ipAddress: $ipAddress,
        );
    }

    private function isEligible(string $phoneNumber, OtpPurpose $purpose): bool
    {
        return User::query()
            ->where('phone_number', $phoneNumber)
            ->when(
                $purpose === OtpPurpose::Register,
                fn ($query) => $query->whereNull('phone_verified_at'),
                fn ($query) => $query->whereNotNull('phone_verified_at'),
            )
            ->exists();
    }
}

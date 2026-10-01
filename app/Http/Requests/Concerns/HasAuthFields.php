<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Support\Digits;
use App\Support\PhoneNumber;
use Illuminate\Validation\Rules\Password;

/**
 * Shared normalization and rules for phone number, OTP code and password fields.
 */
trait HasAuthFields
{
    public function phoneNumber(): string
    {
        return $this->string('phone_number')->toString();
    }

    public function code(): string
    {
        return $this->string('code')->toString();
    }

    /**
     * Optional label for the issued API token (e.g. "iPhone app"), so customers can tell devices apart.
     */
    public function deviceName(): ?string
    {
        return $this->filled('device_name') ? $this->string('device_name')->trim()->toString() : null;
    }

    /**
     * @return list<mixed>
     */
    protected function deviceNameRules(): array
    {
        return ['nullable', 'string', 'max:100'];
    }

    /**
     * Normalize Persian/Arabic digits and phone number prefixes before validation.
     */
    protected function prepareForValidation(): void
    {
        $normalized = [];

        if (is_string($this->input('phone_number'))) {
            $normalized['phone_number'] = PhoneNumber::normalize($this->input('phone_number'));
        }

        if (is_string($this->input('code'))) {
            $normalized['code'] = trim(Digits::toLatin($this->input('code')));
        }

        $this->merge($normalized);
    }

    /**
     * @return list<mixed>
     */
    protected function phoneNumberRules(): array
    {
        return ['required', 'string', 'regex:'.PhoneNumber::PATTERN];
    }

    /**
     * @return list<mixed>
     */
    protected function codeRules(): array
    {
        return ['required', 'string', 'digits:'.config('otp.length')];
    }

    /**
     * @return list<mixed>
     */
    protected function newPasswordRules(): array
    {
        return ['required', 'string', 'confirmed', Password::defaults()];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Describes a code-sending step. Contains no account data, so both API areas share it,
 * and it is identical whether or not a code was actually sent.
 *
 * @property string $resource the normalized phone number
 */
class OtpChallengeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'phone_number' => $this->resource,
            'code_length' => (int) config('otp.length'),
            'expires_in' => (int) config('otp.ttl_seconds'),
            'resend_available_in' => (int) config('otp.resend_cooldown_seconds'),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * An expected authentication failure, rendered with a machine-readable error code.
 */
final class AuthException extends Exception implements ShouldntReport
{
    /**
     * @param  array<string, string>  $headers
     */
    private function __construct(
        public readonly string $errorCode,
        public readonly int $status,
        public readonly ?string $field = null,
        public readonly array $headers = [],
        public readonly int $retryAfter = 0,
    ) {
        parent::__construct($errorCode);
    }

    public static function invalidCredentials(): self
    {
        return new self('invalid_credentials', Response::HTTP_UNPROCESSABLE_ENTITY, 'phone_number');
    }

    public static function phoneNotVerified(): self
    {
        return new self('phone_not_verified', Response::HTTP_FORBIDDEN, 'phone_number');
    }

    public static function accountInactive(): self
    {
        return new self('account_inactive', Response::HTTP_FORBIDDEN, 'phone_number');
    }

    public static function notStaff(): self
    {
        return new self('not_staff', Response::HTTP_FORBIDDEN, 'phone_number');
    }

    public static function otpInvalid(): self
    {
        return new self('otp_invalid', Response::HTTP_UNPROCESSABLE_ENTITY, 'code');
    }

    public static function otpExpired(): self
    {
        return new self('otp_expired', Response::HTTP_UNPROCESSABLE_ENTITY, 'code');
    }

    public static function otpTooManyAttempts(): self
    {
        return new self('otp_too_many_attempts', Response::HTTP_UNPROCESSABLE_ENTITY, 'code');
    }

    public static function otpResendCooldown(int $retryAfterSeconds): self
    {
        return new self(
            'otp_resend_cooldown',
            Response::HTTP_TOO_MANY_REQUESTS,
            headers: ['Retry-After' => (string) $retryAfterSeconds],
            retryAfter: $retryAfterSeconds,
        );
    }

    public function render(): JsonResponse
    {
        $message = __("auth.errors.{$this->errorCode}", ['seconds' => $this->retryAfter]);

        return response()->json(array_filter([
            'message' => $message,
            'code' => $this->errorCode,
            'errors' => $this->field === null ? null : [$this->field => [$message]],
            'retry_after' => $this->retryAfter > 0 ? $this->retryAfter : null,
        ], fn (mixed $value): bool => $value !== null), $this->status, $this->headers);
    }
}

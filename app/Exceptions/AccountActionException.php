<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * A customer or staff change refused by a business rule, rendered with a machine-readable code.
 */
final class AccountActionException extends Exception implements ShouldntReport
{
    private function __construct(
        public readonly string $errorCode,
        public readonly int $status = Response::HTTP_CONFLICT,
        public readonly ?string $field = null,
    ) {
        parent::__construct($errorCode);
    }

    public static function cannotChangeOwnAccount(): self
    {
        return new self('cannot_change_own_account');
    }

    public static function lastSuperAdmin(): self
    {
        return new self('last_super_admin');
    }

    public static function alreadyStaff(): self
    {
        return new self('already_staff', Response::HTTP_UNPROCESSABLE_ENTITY, 'phone_number');
    }

    public static function nameRequiredForNewStaff(): self
    {
        return new self('name_required', Response::HTTP_UNPROCESSABLE_ENTITY, 'name');
    }

    public static function customerAnonymised(): self
    {
        return new self('customer_anonymised');
    }

    public function render(): JsonResponse
    {
        $message = __("admin.errors.{$this->errorCode}");

        return response()->json(array_filter([
            'message' => $message,
            'code' => $this->errorCode,
            'errors' => $this->field === null ? null : [$this->field => [$message]],
        ], fn (mixed $value): bool => $value !== null), $this->status);
    }
}

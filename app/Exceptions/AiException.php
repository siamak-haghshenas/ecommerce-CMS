<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * An AI feature could not answer. The rest of the page keeps working without it.
 */
final class AiException extends Exception
{
    private function __construct(
        public readonly string $errorCode,
        public readonly int $status,
        string $detail = '',
    ) {
        parent::__construct($detail === '' ? $errorCode : "{$errorCode}: {$detail}");
    }

    /**
     * No API key (or a rejected one): the store owner has to configure the provider.
     */
    public static function notConfigured(string $detail = ''): self
    {
        return new self('ai_not_configured', Response::HTTP_SERVICE_UNAVAILABLE, $detail);
    }

    /**
     * The provider is unreachable, overloaded or failing: worth retrying later.
     */
    public static function unavailable(string $detail = ''): self
    {
        return new self('ai_unavailable', Response::HTTP_SERVICE_UNAVAILABLE, $detail);
    }

    /**
     * The provider answered, but not in the requested shape.
     */
    public static function invalidResponse(string $detail = ''): self
    {
        return new self('ai_invalid_response', Response::HTTP_BAD_GATEWAY, $detail);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => __("admin.errors.{$this->errorCode}"),
            'code' => $this->errorCode,
        ], $this->status);
    }
}

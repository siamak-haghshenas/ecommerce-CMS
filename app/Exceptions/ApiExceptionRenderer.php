<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Renders every API error in one shape: { message, code, errors? }.
 */
final class ApiExceptionRenderer
{
    public function __invoke(Throwable $exception, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null;
        }

        return match (true) {
            $exception instanceof ValidationException => $this->respond(
                'validation_failed',
                $exception->status,
                $exception->validator->errors()->first(),
                ['errors' => $exception->errors()],
            ),
            $exception instanceof AuthenticationException => $this->respond('unauthenticated', 401),
            $exception instanceof ThrottleRequestsException => $this->respond(
                'too_many_requests',
                429,
                headers: $exception->getHeaders(),
                extra: ['retry_after' => (int) ($exception->getHeaders()['Retry-After'] ?? 0)],
            ),
            $exception instanceof HttpExceptionInterface => $this->respond(
                $this->codeForStatus($exception->getStatusCode()),
                $exception->getStatusCode(),
                headers: $exception->getHeaders(),
            ),
            config('app.debug') => null,
            default => $this->respond('server_error', 500),
        };
    }

    /**
     * @param  array<string, mixed>  $extra
     * @param  array<string, mixed>  $headers
     */
    private function respond(string $code, int $status, ?string $message = null, array $extra = [], array $headers = []): JsonResponse
    {
        return response()->json([
            'message' => $message ?? __("api.errors.{$code}"),
            'code' => $code,
            ...$extra,
        ], $status, $headers);
    }

    private function codeForStatus(int $status): string
    {
        return match ($status) {
            403 => 'forbidden',
            404 => 'not_found',
            405 => 'method_not_allowed',
            419 => 'csrf_token_mismatch',
            429 => 'too_many_requests',
            503 => 'service_unavailable',
            default => $status >= 500 ? 'server_error' : 'http_error',
        };
    }
}

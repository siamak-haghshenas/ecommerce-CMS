<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * A media library action refused by a rule, rendered with a machine-readable code.
 */
final class MediaException extends Exception implements ShouldntReport
{
    /**
     * @param  array<string, mixed>  $parameters  message parameters
     * @param  array<string, mixed>  $extra  extra response fields
     */
    private function __construct(
        public readonly string $errorCode,
        public readonly int $status,
        public readonly array $parameters = [],
        public readonly ?string $field = null,
        public readonly array $extra = [],
    ) {
        parent::__construct($errorCode);
    }

    /**
     * Products, categories, brands or videos use the file: remove it from them first.
     */
    public static function inUse(int $places): self
    {
        return new self('media_in_use', Response::HTTP_CONFLICT, ['count' => $places]);
    }

    /**
     * The file's real content type is not one the library accepts.
     */
    public static function typeNotAllowed(): self
    {
        return new self('media_type_not_allowed', Response::HTTP_UNPROCESSABLE_ENTITY, field: 'file');
    }

    public static function tooLarge(int $maxBytes): self
    {
        return new self('media_too_large', Response::HTTP_UNPROCESSABLE_ENTITY, ['max' => (int) round($maxBytes / 1024 / 1024)], 'file');
    }

    /**
     * A chunk that doesn't continue where the upload stopped; `received` says where to resume.
     */
    public static function chunkOutOfOrder(int $received): self
    {
        return new self('media_chunk_out_of_order', Response::HTTP_CONFLICT, extra: ['received' => $received]);
    }

    /**
     * More bytes than the upload announced.
     */
    public static function chunkTooLarge(): self
    {
        return new self('media_chunk_too_large', Response::HTTP_UNPROCESSABLE_ENTITY, field: 'chunk');
    }

    public static function uploadIncomplete(): self
    {
        return new self('media_upload_incomplete', Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function render(): JsonResponse
    {
        $message = __("admin.errors.{$this->errorCode}", $this->parameters);

        return response()->json(array_filter([
            'message' => $message,
            'code' => $this->errorCode,
            'errors' => $this->field === null ? null : [$this->field => [$message]],
            ...$this->extra,
        ], fn (mixed $value): bool => $value !== null), $this->status);
    }
}

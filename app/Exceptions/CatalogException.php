<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * An attribute or option change refused by a rule, rendered with a machine-readable code.
 */
final class CatalogException extends Exception implements ShouldntReport
{
    /**
     * @param  array<string, string>  $replace  message placeholders
     */
    private function __construct(
        public readonly string $errorCode,
        public readonly int $status = Response::HTTP_CONFLICT,
        public readonly array $replace = [],
        public readonly ?string $field = null,
    ) {
        parent::__construct($errorCode);
    }

    /**
     * A reorder did not list exactly the current items, so the admin's list is outdated.
     */
    public static function listChanged(): self
    {
        return new self('catalog_list_changed');
    }

    /**
     * Products use the attribute or option, so it can't be deleted.
     */
    public static function inUse(): self
    {
        return new self('catalog_item_in_use');
    }

    /**
     * Products use the value, so it can't be removed.
     */
    public static function valueInUse(string $value): self
    {
        return new self('catalog_value_in_use', Response::HTTP_CONFLICT, ['value' => $value]);
    }

    /**
     * Products have values of this attribute, so its type can't change.
     */
    public static function typeLocked(): self
    {
        return new self('attribute_type_locked', Response::HTTP_UNPROCESSABLE_ENTITY, [], 'type');
    }

    public function render(): JsonResponse
    {
        $message = __("admin.errors.{$this->errorCode}", $this->replace);

        return response()->json(array_filter([
            'message' => $message,
            'code' => $this->errorCode,
            'errors' => $this->field === null ? null : [$this->field => [$message]],
        ], fn (mixed $value): bool => $value !== null), $this->status);
    }
}

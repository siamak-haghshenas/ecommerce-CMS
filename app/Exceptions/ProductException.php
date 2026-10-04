<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * A product change refused by a rule, rendered with a machine-readable code.
 */
final class ProductException extends Exception implements ShouldntReport
{
    private function __construct(
        public readonly string $errorCode,
        public readonly int $status = Response::HTTP_CONFLICT,
        public readonly ?string $field = null,
    ) {
        parent::__construct($errorCode);
    }

    /**
     * The stock changed (e.g. an order) after the admin opened the form: their number is outdated.
     */
    public static function stockChanged(int $variantIndex): self
    {
        return new self('stock_changed', Response::HTTP_CONFLICT, "variants.{$variantIndex}.stock_quantity");
    }

    /**
     * The same, for a variant saved by the bulk edit page.
     */
    public static function bulkStockChanged(int $index): self
    {
        return new self('stock_changed', Response::HTTP_CONFLICT, "variants.{$index}.stock_quantity");
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

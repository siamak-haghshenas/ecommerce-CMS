<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * A sale event change refused by a rule, rendered with a machine-readable code.
 */
final class SaleEventException extends Exception implements ShouldntReport
{
    private function __construct(
        public readonly string $errorCode,
        public readonly int $status = Response::HTTP_CONFLICT,
    ) {
        parent::__construct($errorCode);
    }

    /**
     * A reorder did not list exactly the current events, so the admin's list is outdated.
     */
    public static function listChanged(): self
    {
        return new self('sale_event_list_changed');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => __("admin.errors.{$this->errorCode}"),
            'code' => $this->errorCode,
        ], $this->status);
    }
}

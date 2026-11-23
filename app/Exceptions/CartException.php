<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * A cart change refused by a rule, rendered with a machine-readable code and any extra fields the
 * storefront needs (e.g. `max_quantity`).
 */
final class CartException extends Exception implements ShouldntReport
{
    /**
     * @param  array<string, mixed>  $extra  added to the error body
     * @param  array<string, string>  $replace  for the message
     */
    private function __construct(
        public readonly string $errorCode,
        public readonly int $status = Response::HTTP_UNPROCESSABLE_ENTITY,
        public readonly array $extra = [],
        public readonly array $replace = [],
    ) {
        parent::__construct($errorCode);
    }

    /**
     * The variant doesn't exist, is disabled or deleted, or the storefront doesn't show its product.
     */
    public static function itemUnavailable(): self
    {
        return new self('cart_item_unavailable');
    }

    public static function itemOutOfStock(): self
    {
        return new self('cart_item_out_of_stock');
    }

    /**
     * More than the line may hold (stock, the price's order limit or the store's cap).
     */
    public static function quantityExceeded(int $maxQuantity): self
    {
        return new self('cart_quantity_exceeded', extra: ['max_quantity' => $maxQuantity], replace: ['max' => (string) $maxQuantity]);
    }

    public static function full(int $maxLines): self
    {
        return new self('cart_full', extra: ['max_lines' => $maxLines], replace: ['max' => (string) $maxLines]);
    }

    /**
     * Coupons depend on the customer: guests log in first.
     */
    public static function loginRequired(): self
    {
        return new self('cart_login_required');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => __("cart.errors.{$this->errorCode}", $this->replace),
            'code' => $this->errorCode,
            ...$this->extra,
        ], $this->status);
    }
}

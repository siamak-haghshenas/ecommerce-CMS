<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * A coupon change refused by a rule (admin), or a code that can't be used on a cart (storefront),
 * rendered with a machine-readable code and any extra fields the client needs.
 */
final class CouponException extends Exception implements ShouldntReport
{
    /**
     * @param  array<string, mixed>  $extra  added to the error body (e.g. `min_order_amount`)
     * @param  array<string, string>  $replace  for the message
     */
    private function __construct(
        public readonly string $errorCode,
        public readonly string $messageKey,
        public readonly int $status = Response::HTTP_UNPROCESSABLE_ENTITY,
        public readonly array $extra = [],
        public readonly array $replace = [],
    ) {
        parent::__construct($errorCode);
    }

    /**
     * Orders used the coupon and point at it: it can only be switched off.
     */
    public static function inUse(): self
    {
        return new self('coupon_in_use', 'admin.errors.coupon_in_use', Response::HTTP_CONFLICT);
    }

    /**
     * No coupon has this code (deleted ones included: their code means nothing any more).
     */
    public static function notFound(): self
    {
        return self::refused('coupon_not_found');
    }

    public static function inactive(): self
    {
        return self::refused('coupon_inactive');
    }

    public static function notStarted(string $startsAt): self
    {
        return self::refused('coupon_not_started', ['starts_at' => $startsAt]);
    }

    public static function expired(): self
    {
        return self::refused('coupon_expired');
    }

    /**
     * The usage limit is reached.
     */
    public static function limitReached(): self
    {
        return self::refused('coupon_limit_reached');
    }

    /**
     * The coupon is for chosen customers and this one isn't among them.
     */
    public static function notForCustomer(): self
    {
        return self::refused('coupon_not_for_customer');
    }

    /**
     * The eligible items add up to less than the coupon's minimum (Rials).
     */
    public static function minOrder(int $minOrderAmount): self
    {
        return self::refused('coupon_min_order', ['min_order_amount' => $minOrderAmount], ['amount' => number_format($minOrderAmount)]);
    }

    public static function noEligibleItems(): self
    {
        return self::refused('coupon_no_eligible_items');
    }

    /**
     * @param  array<string, mixed>  $extra
     * @param  array<string, string>  $replace
     */
    private static function refused(string $code, array $extra = [], array $replace = []): self
    {
        return new self($code, "coupons.errors.{$code}", Response::HTTP_UNPROCESSABLE_ENTITY, $extra, $replace);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => __($this->messageKey, $this->replace),
            'code' => $this->errorCode,
            ...$this->extra,
        ], $this->status);
    }
}

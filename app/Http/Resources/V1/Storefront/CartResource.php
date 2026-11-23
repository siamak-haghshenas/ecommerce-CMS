<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Storefront;

use App\Data\Cart\CartCoupon;
use App\Data\Cart\CartLine;
use App\Data\Cart\CartSummary;
use App\Enums\CartLineProblem;
use App\Models\MediaAsset;
use App\Models\OptionValue;
use App\Models\ProductVariant;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A cart as the storefront shows it. Amounts are Rials, sale-aware; lines that can't be bought
 * count in no total. Stock quantities are never exposed (only each line's `max_quantity`).
 * `meta.cart_token` is set only when this request opened a guest cart: the storefront keeps it
 * and sends it back in `X-Cart-Token`.
 *
 * @property CartSummary $resource
 */
class CartResource extends JsonResource
{
    private ?string $newToken = null;

    public function withNewToken(?string $token): self
    {
        $this->newToken = $token;

        return $this;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $summary = $this->resource;

        return [
            'lines' => array_map(fn (CartLine $line): array => $this->line($line), $summary->lines),
            'lines_count' => count($summary->lines),
            'items_count' => $summary->itemsCount(),
            'regular_subtotal' => $summary->regularSubtotal(),
            // What sales, sale events and promotions take off («سود شما از خرید»).
            'savings' => $summary->savings(),
            'items_subtotal' => $summary->itemsSubtotal(),
            'coupon' => $summary->coupon === null ? null : $this->coupon($summary->coupon),
            // The items after the coupon, before shipping.
            'total' => $summary->total(),
            'can_checkout' => $summary->canCheckout(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        $currency = app(Settings::class)->priceDisplayCurrency();

        return ['meta' => [
            'currency' => ['display' => $currency->value, 'rials_per_unit' => $currency->rialsPerUnit()],
            'cart_token' => $this->newToken,
        ]];
    }

    /**
     * @return array<string, mixed>
     */
    private function line(CartLine $line): array
    {
        $variant = $line->variant;
        $product = $variant?->product;

        return [
            'variant_id' => $line->item->product_variant_id,
            'sku' => $variant?->sku,
            'product' => $product === null ? null : [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
            ],
            'image' => $variant === null ? null : $this->image($variant),
            'options' => $variant === null ? [] : $this->options($variant),
            'quantity' => $line->quantity(),
            // The most units this line may hold now (stock, the price's order limit, the store's cap); 0 when it can't be bought.
            'max_quantity' => $line->maxQuantity,
            // The quantity asked for before a merge cut it to `max_quantity` (with the `exceeds_limit` problem).
            'limited_from' => $line->item->limited_from,
            'unit_price' => $line->unitPrice,
            'regular_price' => $line->regularPrice,
            'discount_percent' => $variant?->discountPercent() ?? 0,
            'discount_source' => $variant?->discount_source?->value,
            // The price the customer saw before, when it changed (with the `price_changed` problem).
            'price_was' => $line->priceWas,
            'total' => $line->total(),
            'regular_total' => $line->regularTotal(),
            'problems' => array_map(fn (CartLineProblem $problem): string => $problem->value, $line->problems),
        ];
    }

    /**
     * The variant's own picture, else the product's main one.
     *
     * @return array{thumb: string|null, medium: string|null, alt: string|null}|null
     */
    private function image(ProductVariant $variant): ?array
    {
        $gallery = $variant->product?->galleryAssets;
        $asset = $gallery?->firstWhere('id', $variant->image_asset_id) ?? $gallery?->first();
        $picture = $asset instanceof MediaAsset ? $asset->picture() : null;

        return $picture === null ? null : ['thumb' => $picture['thumb'], 'medium' => $picture['medium'], 'alt' => $picture['alt']];
    }

    /**
     * The chosen option values, in the options' order (e.g. رنگ: مشکی).
     *
     * @return list<array{name: string, value: string}>
     */
    private function options(ProductVariant $variant): array
    {
        return $variant->optionValues
            ->sortBy(fn (OptionValue $value): int => (int) $value->option?->position)
            ->map(fn (OptionValue $value): array => ['name' => (string) $value->option?->name, 'value' => $value->value])
            ->values()
            ->all();
    }

    /**
     * The cart's coupon checked again now; with a problem it takes nothing off (the code stays).
     *
     * @return array<string, mixed>
     */
    private function coupon(CartCoupon $coupon): array
    {
        $problem = $coupon->problem;

        return [
            'code' => $coupon->code,
            'type' => $coupon->checked?->coupon->type->value,
            'discount' => $coupon->discount(),
            'free_shipping' => $coupon->freeShipping(),
            'ends_at' => $coupon->checked?->coupon->ends_at?->toIso8601String(),
            // A coupon error code (`coupon_min_order`, `coupon_expired`, ...) with its message and fields.
            'problem' => $problem === null ? null : [
                'code' => $problem->errorCode,
                'message' => __($problem->messageKey, $problem->replace),
                ...$problem->extra,
            ],
        ];
    }
}

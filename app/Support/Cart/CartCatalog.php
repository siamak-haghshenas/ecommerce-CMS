<?php

declare(strict_types=1);

namespace App\Support\Cart;

use App\Enums\CartLineProblem;
use App\Models\ProductVariant;
use App\Support\Catalog\StorefrontProductQuery;
use Illuminate\Database\Eloquent\Collection;

/**
 * The variants of a cart as the storefront sells them now: whether each can be bought and how
 * many units one line may hold.
 *
 * A variant is unavailable when it is missing, deleted or disabled, or the storefront doesn't
 * show its product; out of stock when stock is tracked, backorders are off and nothing is left.
 * A line holds at most the store's cap (`cart.max_quantity_per_line`), the order limit of the
 * sale event or promotion giving the price (`max_per_order`) and, for tracked stock without
 * backorders, the stock left.
 */
final readonly class CartCatalog
{
    /**
     * @param  Collection<int, ProductVariant>  $variants  by id
     * @param  list<int>  $visibleProductIds
     */
    private function __construct(
        private Collection $variants,
        private array $visibleProductIds,
    ) {}

    /**
     * @param  list<int>  $variantIds
     * @param  bool  $withDetails  also what a cart shows: options and pictures
     */
    public static function load(array $variantIds, bool $withDetails = false): self
    {
        $variants = ProductVariant::query()
            ->withTrashed()
            ->with($withDetails ? ['product.galleryAssets', 'optionValues.option'] : ['product'])
            ->whereKey($variantIds)
            ->get()
            ->keyBy('id');

        $productIds = $variants->pluck('product_id')->unique()->values()->all();
        $visible = $productIds === [] ? [] : StorefrontProductQuery::visible()->whereKey($productIds)->pluck('id')->all();

        return new self($variants, array_map(intval(...), $visible));
    }

    public function variant(int $variantId): ?ProductVariant
    {
        return $this->variants->get($variantId);
    }

    /**
     * Why the variant can't be bought now, if it can't.
     */
    public function problem(int $variantId): ?CartLineProblem
    {
        $variant = $this->variant($variantId);
        $product = $variant?->product;

        if ($variant === null || $product === null || $variant->trashed() || ! $variant->is_enabled
            || ! in_array($product->id, $this->visibleProductIds, true)) {
            return CartLineProblem::Unavailable;
        }

        return $product->isAvailable($variant) ? null : CartLineProblem::OutOfStock;
    }

    /**
     * The most units one line of the variant may hold now (0 when it can't be bought).
     */
    public function maxQuantity(int $variantId): int
    {
        if ($this->problem($variantId) !== null) {
            return 0;
        }

        $variant = $this->variant($variantId);
        assert($variant !== null && $variant->product !== null);

        $limits = [(int) config('cart.max_quantity_per_line')];

        if ($variant->max_per_order !== null) {
            $limits[] = $variant->max_per_order;
        }

        if ($variant->product->track_stock && ! $variant->product->allow_backorders) {
            $limits[] = (int) $variant->stock_quantity;
        }

        return max(0, min($limits));
    }
}

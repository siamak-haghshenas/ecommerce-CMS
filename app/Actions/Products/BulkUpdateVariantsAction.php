<?php

declare(strict_types=1);

namespace App\Actions\Products;

use App\Actions\Pricing\RefreshPricesAction;
use App\Data\Products\BulkVariantChange;
use App\Enums\StockMovementReason;
use App\Exceptions\ProductException;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Saves the bulk editor's page: every changed variant in one transaction (all or nothing).
 * A sale added here starts now with no end date; removing a sale clears its dates. Stock changes
 * go through AdjustStockAction (reason "bulk_edit"); each changed product gets one audit entry.
 */
final readonly class BulkUpdateVariantsAction
{
    public function __construct(
        private AdjustStockAction $adjustStock,
        private RefreshPricesAction $refreshPrices,
    ) {}

    /**
     * @param  list<BulkVariantChange>  $changes
     * @return list<int> the ids of the variants that changed
     *
     * @throws ProductException when stock changed after validation
     */
    public function handle(User $actor, array $changes): array
    {
        return DB::transaction(function () use ($actor, $changes): array {
            $variants = ProductVariant::query()
                ->whereKey(array_map(fn (BulkVariantChange $change): int => $change->variantId, $changes))
                ->with('product')
                ->get()
                ->keyBy('id');
            $changed = [];

            foreach ($changes as $index => $change) {
                /** @var ProductVariant $variant */
                $variant = $variants[$change->variantId];

                if ($this->apply($actor, $variant, $change, $index)) {
                    $changed[] = $variant->id;
                }
            }

            $products = $variants->only($changed)->map(fn (ProductVariant $variant): Product => $variant->product)->unique('id')->values()->all();
            $this->log($actor, $products);
            // A changed price or sale may lose to (or beat) a promotion.
            $this->refreshPrices->handle(array_map(fn (Product $product): int => $product->id, $products));

            return $changed;
        });
    }

    private function apply(User $actor, ProductVariant $variant, BulkVariantChange $change, int $index): bool
    {
        $hadSale = $variant->sale_price !== null;
        $variant->fill($change->attributes);

        // A new sale starts now and has no end; a removed sale keeps no dates.
        if (array_key_exists('sale_price', $change->attributes) && ($variant->sale_price === null || ! $hadSale)) {
            $variant->sale_starts_at = null;
            $variant->sale_ends_at = null;
        }

        $isChanged = $variant->isDirty();
        $variant->save();

        if ($change->stockQuantity === null) {
            return $isChanged;
        }

        // Checked again under the row lock: an order may have come in after validation.
        $current = (int) ProductVariant::query()->whereKey($variant->id)->lockForUpdate()->value('stock_quantity');

        if ($current !== $change->stockQuantityWas) {
            throw ProductException::bulkStockChanged($index);
        }

        if ($change->stockQuantity !== $current) {
            $this->adjustStock->handle($variant, $change->stockQuantity - $current, StockMovementReason::BulkEdit, $actor);
            $isChanged = true;
        }

        return $isChanged;
    }

    /**
     * @param  list<Product>  $products
     */
    private function log(User $actor, array $products): void
    {
        foreach ($products as $product) {
            $product->touch();

            activity('admin')
                ->causedBy($actor)
                ->performedOn($product)
                ->event('product.updated')
                ->withProperties(['name' => $product->name, 'fields' => ['variants'], 'bulk' => true])
                ->log('product.updated');
        }
    }
}

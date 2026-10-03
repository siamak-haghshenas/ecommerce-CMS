<?php

declare(strict_types=1);

namespace App\Actions\Products;

use App\Enums\StockMovementReason;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The only way a variant's stock changes: the row is locked, the quantity changed and the
 * change recorded in stock_movements (who, why, before → after).
 */
final class AdjustStockAction
{
    /**
     * Change the stock by $change (negative to remove). Returns the new quantity.
     */
    public function handle(
        ProductVariant $variant,
        int $change,
        StockMovementReason $reason,
        ?User $actor = null,
        ?string $note = null,
        ?Model $reference = null,
    ): int {
        if ($change === 0) {
            return $variant->stock_quantity;
        }

        return DB::transaction(function () use ($variant, $change, $reason, $actor, $note, $reference): int {
            $before = (int) ProductVariant::withTrashed()->whereKey($variant->id)->lockForUpdate()->value('stock_quantity');
            $after = $before + $change;

            ProductVariant::withTrashed()->whereKey($variant->id)->update(['stock_quantity' => $after]);
            $variant->forceFill(['stock_quantity' => $after])->syncOriginalAttribute('stock_quantity');

            StockMovement::query()->create([
                'product_variant_id' => $variant->id,
                'user_id' => $actor?->id,
                'reason' => $reason,
                'quantity_change' => $change,
                'quantity_before' => $before,
                'quantity_after' => $after,
                'note' => $note,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
            ]);

            return $after;
        });
    }
}

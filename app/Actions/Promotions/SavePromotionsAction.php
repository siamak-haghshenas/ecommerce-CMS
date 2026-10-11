<?php

declare(strict_types=1);

namespace App\Actions\Promotions;

use App\Actions\Pricing\RefreshPricesAction;
use App\Data\Promotions\PromotionRowData;
use App\Data\Promotions\SavePromotionsData;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class SavePromotionsAction
{
    public function __construct(private RefreshPricesAction $refreshPrices) {}

    /**
     * Creates the new rows, updates the changed ones and deletes the removed ones in one go, logs
     * each change and reprices the products concerned.
     */
    public function handle(User $actor, SavePromotionsData $data): void
    {
        $productIds = DB::transaction(function () use ($actor, $data): array {
            $productIds = [];
            $saved = Promotion::query()->whereKey(array_filter(array_map(fn (PromotionRowData $row): ?int => $row->id, $data->rows)))->get()->keyBy('id');
            $variantProducts = ProductVariant::withTrashed()
                ->whereKey(array_map(fn (PromotionRowData $row): int => $row->variantId, $data->rows))
                ->pluck('product_id', 'id');

            foreach ($data->rows as $row) {
                $creating = $row->id === null;
                /** @var Promotion $promotion */
                $promotion = $creating
                    ? new Promotion([
                        'product_id' => (int) $variantProducts[$row->variantId],
                        'product_variant_id' => $row->variantId,
                        'created_by' => $actor->id,
                    ])
                    : $saved[$row->id];

                $promotion->fill([
                    'discount_type' => $row->discountType,
                    'discount_value' => $row->discountValue,
                    'max_per_order' => $row->maxPerOrder,
                    'quantity_limit' => $row->quantityLimit,
                    'starts_at' => $row->startsAt,
                    'ends_at' => $row->endsAt,
                ]);

                if (! $creating && ! $promotion->isDirty()) {
                    continue;
                }

                $fields = array_values(array_diff(array_keys($promotion->getDirty()), ['product_id', 'product_variant_id', 'created_by']));
                $promotion->updated_by = $actor->id;
                $promotion->save();
                $productIds[] = $promotion->product_id;

                $this->log($actor, $promotion, $creating ? 'promotion.created' : 'promotion.updated', $creating ? [] : ['fields' => $fields]);
            }

            foreach (Promotion::query()->whereKey($data->deletedIds)->with('variant')->get() as $promotion) {
                $this->log($actor, $promotion, 'promotion.deleted');
                $productIds[] = $promotion->product_id;
                $promotion->delete();
            }

            return $productIds;
        });

        $this->refreshPrices->handle(array_values(array_unique($productIds)));
    }

    /**
     * Audit entry naming the SKU (no personal data); a deleted row keeps it in the properties.
     *
     * @param  array<string, mixed>  $properties
     */
    private function log(User $actor, Promotion $promotion, string $event, array $properties = []): void
    {
        activity('admin')
            ->causedBy($actor)
            ->performedOn($promotion)
            ->event($event)
            ->withProperties(['sku' => $promotion->variant->sku, ...$properties])
            ->log($event);
    }
}

<?php

declare(strict_types=1);

namespace App\Support\Pricing;

use App\Models\SaleEventItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The sale event items prices depend on (enabled events that haven't ended, items that haven't
 * ended), by variant and highest priority first.
 */
final class SaleEventSet
{
    /** @var array<int, list<SaleEventItem>> */
    private array $byVariant = [];

    /**
     * @param  Collection<int, SaleEventItem>  $items  with `saleEvent` loaded
     */
    public function __construct(Collection $items)
    {
        $sorted = $items->sortBy([
            fn (SaleEventItem $a, SaleEventItem $b): int => $a->saleEvent->position <=> $b->saleEvent->position,
            fn (SaleEventItem $a, SaleEventItem $b): int => $a->sale_event_id <=> $b->sale_event_id,
        ]);

        foreach ($sorted as $item) {
            $this->byVariant[$item->product_variant_id][] = $item;
        }
    }

    /**
     * @param  list<int>|null  $productIds  null: every product
     */
    public static function load(?array $productIds = null): self
    {
        $now = now();

        return new self(SaleEventItem::query()
            ->whereHas('saleEvent', fn (Builder $events) => $events->relevant())
            ->where(fn (Builder $end) => $end->whereNull('ends_at')->orWhere('ends_at', '>', $now))
            ->when($productIds !== null, fn (Builder $query) => $query->whereIn('product_id', $productIds))
            ->with('saleEvent')
            ->get());
    }

    /**
     * The variant's items, highest priority first.
     *
     * @return list<SaleEventItem>
     */
    public function forVariant(int $variantId): array
    {
        return $this->byVariant[$variantId] ?? [];
    }

    /**
     * The products in these events (the ones to reprice when they change).
     *
     * @param  list<int>  $eventIds
     * @return list<int>
     */
    public static function productIdsIn(array $eventIds): array
    {
        return $eventIds === [] ? [] : SaleEventItem::query()
            ->whereIn('sale_event_id', $eventIds)
            ->distinct()
            ->pluck('product_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\SaleEvents;

use App\Data\SaleEvents\SaleEventFilters;
use App\Models\SaleEvent;
use App\Models\SaleEventItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class SearchSaleEventsAction
{
    /**
     * The admin event list in priority order (not paginated: the order is managed by hand), with
     * how many variants and products each has.
     *
     * @return Collection<int, SaleEvent>
     */
    public function handle(SaleEventFilters $filters): Collection
    {
        return SaleEvent::query()
            ->addSelect(['products_count' => SaleEventItem::query()
                ->selectRaw('COUNT(DISTINCT product_id)')
                ->whereColumn('sale_event_items.sale_event_id', 'sale_events.id')])
            ->withCount('items')
            ->when($filters->search !== null, function (Builder $query) use ($filters): void {
                $search = '%'.$filters->search.'%';
                $query->where(fn (Builder $match) => $match->where('name', 'like', $search)->orWhere('slug', 'like', $search));
            })
            ->when($filters->state !== null, fn (Builder $query) => $query->inState((string) $filters->state))
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }
}

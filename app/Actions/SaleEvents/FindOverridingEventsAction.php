<?php

declare(strict_types=1);

namespace App\Actions\SaleEvents;

use App\Models\SaleEvent;
use App\Models\SaleEventItem;
use Illuminate\Database\Eloquent\Builder;

final class FindOverridingEventsAction
{
    /**
     * For each variant of the event: the enabled events of higher priority that hold the same
     * variant at overlapping times, so the admin sees where this event won't be used.
     *
     * @return array<int, list<array{id: int, name: string, starts_at: string, ends_at: string}>> by variant id
     */
    public function handle(SaleEvent $saleEvent): array
    {
        $own = $saleEvent->items->each->setRelation('saleEvent', $saleEvent)->keyBy('product_variant_id');

        if ($own->isEmpty()) {
            return [];
        }

        $higher = SaleEventItem::query()
            ->whereIn('product_variant_id', $own->keys()->all())
            ->whereHas('saleEvent', fn (Builder $events) => $events
                ->where('is_active', true)
                ->whereKeyNot($saleEvent->id)
                ->where(fn (Builder $priority) => $priority
                    ->where('position', '<', $saleEvent->position)
                    ->orWhere(fn (Builder $tie) => $tie->where('position', $saleEvent->position)->where('id', '<', $saleEvent->id))))
            ->with('saleEvent')
            ->get();

        $overriding = [];

        foreach ($higher as $other) {
            /** @var SaleEventItem $mine */
            $mine = $own->get($other->product_variant_id);
            $from = max($mine->startsAt(), $other->startsAt());
            $to = min($mine->endsAt(), $other->endsAt());

            if ($from->lt($to)) {
                $overriding[$other->product_variant_id][] = [
                    'id' => $other->sale_event_id,
                    'name' => $other->saleEvent->name,
                    'starts_at' => $from->toIso8601String(),
                    'ends_at' => $to->toIso8601String(),
                ];
            }
        }

        return $overriding;
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\SaleEvents;

use App\Actions\Pricing\RefreshPricesAction;
use App\Exceptions\SaleEventException;
use App\Models\SaleEvent;
use App\Models\User;
use App\Support\Pricing\SaleEventSet;
use Illuminate\Support\Facades\DB;

final readonly class ReorderSaleEventsAction
{
    public function __construct(private RefreshPricesAction $refreshPrices) {}

    /**
     * Save a new priority order (first = highest). The ids must be exactly the current events, so
     * an admin working from an outdated list can't silently misplace events added meanwhile.
     * Products of events that haven't ended are repriced, as another event may now win.
     *
     * @param  list<int>  $ids
     *
     * @throws SaleEventException
     */
    public function handle(User $actor, array $ids): void
    {
        $changed = DB::transaction(function () use ($actor, $ids): bool {
            $events = SaleEvent::query()->lockForUpdate()->get()->keyBy('id');

            $current = $events->keys()->all();
            sort($current);
            $sent = $ids;
            sort($sent);

            if ($current !== $sent) {
                throw SaleEventException::listChanged();
            }

            $changed = false;

            foreach ($ids as $position => $id) {
                /** @var SaleEvent $event */
                $event = $events->get($id);
                $event->position = $position;

                if ($event->isDirty()) {
                    $event->save();
                    $changed = true;
                }
            }

            if ($changed) {
                activity('admin')->causedBy($actor)->event('sale_event.reordered')->log('sale_event.reordered');
            }

            return $changed;
        });

        if ($changed) {
            $this->refreshPrices->handle(SaleEventSet::productIdsIn(SaleEvent::query()->relevant()->pluck('id')->map(fn (mixed $id): int => (int) $id)->all()));
        }
    }
}

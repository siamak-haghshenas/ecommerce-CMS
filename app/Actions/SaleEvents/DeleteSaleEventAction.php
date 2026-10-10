<?php

declare(strict_types=1);

namespace App\Actions\SaleEvents;

use App\Actions\Pricing\RefreshPricesAction;
use App\Models\SaleEvent;
use App\Models\User;
use App\Support\Pricing\SaleEventSet;

final readonly class DeleteSaleEventAction
{
    public function __construct(private RefreshPricesAction $refreshPrices) {}

    /**
     * Delete an event for good (its landing page disappears and its library files are freed); its
     * products go back to their best other price.
     */
    public function handle(User $actor, SaleEvent $saleEvent): void
    {
        $productIds = SaleEventSet::productIdsIn([$saleEvent->id]);

        $saleEvent->delete();
        $this->refreshPrices->handle($productIds);

        // Event names are not personal data; keeping it makes the entry readable after deletion.
        activity('admin')
            ->causedBy($actor)
            ->event('sale_event.deleted')
            ->withProperties(['name' => $saleEvent->name])
            ->log('sale_event.deleted');
    }
}

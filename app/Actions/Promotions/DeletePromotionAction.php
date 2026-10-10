<?php

declare(strict_types=1);

namespace App\Actions\Promotions;

use App\Actions\Pricing\RefreshPricesAction;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\User;

final readonly class DeletePromotionAction
{
    public function __construct(private RefreshPricesAction $refreshPrices) {}

    /**
     * Delete a promotion; the products it discounted go back to their best other price.
     */
    public function handle(User $actor, Promotion $promotion): void
    {
        $productIds = ProductVariant::query()
            ->where('promotion_id', $promotion->id)
            ->distinct()
            ->pluck('product_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $promotion->delete();
        $this->refreshPrices->handle($productIds);

        // Promotion names are not personal data; keeping it makes the entry readable after deletion.
        activity('admin')
            ->causedBy($actor)
            ->event('promotion.deleted')
            ->withProperties(['name' => $promotion->name])
            ->log('promotion.deleted');
    }
}

<?php

declare(strict_types=1);

namespace App\Support\Pricing;

use App\Models\Promotion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The promotion rows prices depend on (not ended), by variant.
 */
final class PromotionSet
{
    /** @var array<int, list<Promotion>> by variant id */
    private array $byVariant = [];

    /**
     * @param  Collection<int, Promotion>  $promotions
     */
    public function __construct(Collection $promotions)
    {
        foreach ($promotions as $promotion) {
            $this->byVariant[$promotion->product_variant_id][] = $promotion;
        }
    }

    /**
     * @param  list<int>|null  $productIds  null: every product
     */
    public static function load(?array $productIds = null): self
    {
        return new self(Promotion::query()
            ->relevant()
            ->when($productIds !== null, fn (Builder $query) => $query->whereIn('product_id', $productIds))
            ->get());
    }

    /**
     * @return list<Promotion>
     */
    public function forVariant(int $variantId): array
    {
        return $this->byVariant[$variantId] ?? [];
    }
}

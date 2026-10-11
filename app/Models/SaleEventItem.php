<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PromotionType;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One variant of a sale event: a percentage or a fixed amount off its regular price, during the
 * event or a narrower window of its own (`starts_at` / `ends_at` inside the event's dates).
 */
#[Fillable(['sale_event_id', 'product_id', 'product_variant_id', 'discount_type', 'discount_value', 'max_per_order', 'starts_at', 'ends_at'])]
class SaleEventItem extends Model
{
    public $timestamps = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sale_event_id' => 'integer',
            'product_id' => 'integer',
            'product_variant_id' => 'integer',
            // A percentage (1–90) or Rials off each unit.
            'discount_type' => PromotionType::class,
            'discount_value' => 'integer',
            // The most units of the variant one order may hold at the event's price; null: no limit.
            'max_per_order' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<SaleEvent, $this>
     */
    public function saleEvent(): BelongsTo
    {
        return $this->belongsTo(SaleEvent::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id')->withTrashed();
    }

    /**
     * When the discount starts: the item's own start, else the event's (needs `saleEvent`).
     */
    public function startsAt(): CarbonInterface
    {
        return $this->starts_at ?? $this->saleEvent->starts_at;
    }

    /**
     * When the discount ends: the item's own end, else the event's (needs `saleEvent`).
     */
    public function endsAt(): CarbonInterface
    {
        return $this->ends_at ?? $this->saleEvent->ends_at;
    }

    /**
     * Whether the discount runs at that moment: the event is enabled and the moment is inside the
     * item's window (needs `saleEvent`).
     */
    public function runsAt(CarbonInterface $at): bool
    {
        return $this->saleEvent->is_active && $this->startsAt()->lte($at) && $this->endsAt()->gt($at);
    }
}

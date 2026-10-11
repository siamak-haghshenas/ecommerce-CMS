<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PromotionType;
use Carbon\CarbonInterface;
use Database\Factories\PromotionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One SKU's discount: a percentage or a fixed amount off its regular price between `starts_at`
 * and `ends_at`, with an optional order limit (`max_per_order`) and number of discounted units in
 * all (`quantity_limit`; `sold_count` is counted by orders). A variant's rows never overlap in
 * time. The price competes with the variant's sale price and sale events (the lowest wins; see
 * App\Support\Pricing\Pricing).
 */
#[Fillable([
    'product_id', 'product_variant_id', 'discount_type', 'discount_value', 'max_per_order', 'quantity_limit',
    'starts_at', 'ends_at', 'created_by', 'updated_by',
])]
class Promotion extends Model
{
    /** @use HasFactory<PromotionFactory> */
    use HasFactory;

    public const int MAX_PERCENT = 90;

    /** The highest order limit a row may set. */
    public const int MAX_PER_ORDER = 10000;

    /** The highest number of discounted units a row may set. */
    public const int MAX_QUANTITY = 1000000;

    /** The most rows one save may send. */
    public const int MAX_PER_SAVE = 1000;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'product_variant_id' => 'integer',
            'discount_type' => PromotionType::class,
            'discount_value' => 'integer',
            'max_per_order' => 'integer',
            'quantity_limit' => 'integer',
            'sold_count' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
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
     * Rows prices still depend on: not ended.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function relevant(Builder $query): void
    {
        $query->where('ends_at', '>', now());
    }

    public function isSoldOut(): bool
    {
        return $this->quantity_limit !== null && $this->sold_count >= $this->quantity_limit;
    }

    /**
     * Discounted units left; null: no limit.
     */
    public function remaining(): ?int
    {
        return $this->quantity_limit === null ? null : max(0, $this->quantity_limit - $this->sold_count);
    }

    /**
     * "scheduled", "active", "sold_out" or "ended".
     */
    public function state(?CarbonInterface $at = null): string
    {
        $at ??= now();

        return match (true) {
            $this->ends_at->lte($at) => 'ended',
            $this->isSoldOut() => 'sold_out',
            $this->starts_at->gt($at) => 'scheduled',
            default => 'active',
        };
    }

    /**
     * Whether it gives the variant its discount at $at.
     */
    public function runsAt(CarbonInterface $at): bool
    {
        return $this->state($at) === 'active';
    }
}

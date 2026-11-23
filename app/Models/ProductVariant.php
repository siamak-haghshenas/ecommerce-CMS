<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DiscountSource;
use Carbon\CarbonInterface;
use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A sellable SKU. Prices are whole Rials. Stock only changes through AdjustStockAction,
 * which records every change in stock_movements. Its picture (`image_asset_id`) is one of the
 * product's gallery files. The price shoppers pay now is stored in `current_price` (the lowest
 * of the regular price, the sale price and the sale event that applies; see App\Support\Pricing),
 * with `max_per_order`, the order limit of the event or promotion giving it.
 */
#[Fillable([
    'product_id', 'sku', 'price', 'sale_price', 'sale_starts_at', 'sale_ends_at', 'is_enabled',
    'image_asset_id', 'position',
])]
class ProductVariant extends Model
{
    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'price' => 'integer',
            'sale_price' => 'integer',
            'sale_starts_at' => 'datetime',
            'sale_ends_at' => 'datetime',
            'stock_quantity' => 'integer',
            'is_enabled' => 'boolean',
            'image_asset_id' => 'integer',
            'position' => 'integer',
            'current_price' => 'integer',
            'discount_source' => DiscountSource::class,
            'sale_event_id' => 'integer',
            'promotion_id' => 'integer',
            'max_per_order' => 'integer',
            'price_recalc_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Prices and sales are saved through products; sale events are added afterwards by
        // RefreshPricesAction. Until then the stored price follows the variant's own sale.
        static::saving(function (self $variant): void {
            if ($variant->current_price !== null && ! $variant->isDirty(['price', 'sale_price', 'sale_starts_at', 'sale_ends_at'])) {
                return;
            }

            $onSale = $variant->isOnSale();
            $future = array_filter(
                $variant->sale_price === null ? [] : [$variant->sale_starts_at, $variant->sale_ends_at],
                fn (?CarbonInterface $moment): bool => $moment !== null && $moment->isFuture(),
            );

            $variant->forceFill([
                'current_price' => $onSale ? $variant->sale_price : $variant->price,
                'discount_source' => $onSale ? DiscountSource::Sale : null,
                'sale_event_id' => null,
                'promotion_id' => null,
                'max_per_order' => null,
                'price_recalc_at' => $future === [] ? null : min($future),
            ]);
        });
    }

    /**
     * The sale event that gives the current price, if one does.
     *
     * @return BelongsTo<SaleEvent, $this>
     */
    public function saleEvent(): BelongsTo
    {
        return $this->belongsTo(SaleEvent::class);
    }

    /**
     * The promotion row that gives the current price, if one does.
     *
     * @return BelongsTo<Promotion, $this>
     */
    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    /**
     * Every promotion row of the variant (any dates).
     *
     * @return HasMany<Promotion, $this>
     */
    public function promotions(): HasMany
    {
        return $this->hasMany(Promotion::class);
    }

    /**
     * The variant's place in sale events (past ones included).
     *
     * @return HasMany<SaleEventItem, $this>
     */
    public function saleEventItems(): HasMany
    {
        return $this->hasMany(SaleEventItem::class);
    }

    /**
     * Variants whose price is due to change (a sale, sale event or promotion started or ended).
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function priceDue(Builder $query): void
    {
        $query->where('price_recalc_at', '<=', now());
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /**
     * @return BelongsToMany<OptionValue, $this>
     */
    public function optionValues(): BelongsToMany
    {
        return $this->belongsToMany(OptionValue::class);
    }

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Whether the sale price applies now (or at the given moment).
     */
    public function isOnSale(?CarbonInterface $at = null): bool
    {
        $at ??= now();

        return $this->sale_price !== null
            && $this->sale_price < $this->price
            && ($this->sale_starts_at === null || $this->sale_starts_at->lte($at))
            && ($this->sale_ends_at === null || $this->sale_ends_at->gt($at));
    }

    /**
     * The price shoppers pay now (stored; see the class description).
     */
    public function currentPrice(): int
    {
        return $this->current_price ?? $this->price;
    }

    /**
     * Whether a sale, a sale event or a promotion lowers the price now.
     */
    public function isDiscounted(): bool
    {
        return $this->currentPrice() < $this->price;
    }

    /**
     * When the discount giving the current price ends: the sale's end, the promotion row's, or the
     * sale event item's (else the event's). Null without a discount or for a sale with no end date.
     */
    public function discountEndsAt(): ?CarbonInterface
    {
        if (! $this->isDiscounted()) {
            return null;
        }

        return match ($this->discount_source) {
            DiscountSource::Sale => $this->sale_ends_at,
            DiscountSource::Promotion => $this->promotion?->ends_at,
            DiscountSource::SaleEvent => $this->saleEvent === null ? null
                : $this->saleEventItems->firstWhere('sale_event_id', $this->sale_event_id)->ends_at ?? $this->saleEvent->ends_at,
            default => null,
        };
    }

    /**
     * The discount in whole percent, else 0.
     */
    public function discountPercent(): int
    {
        if (! $this->isDiscounted() || $this->price === 0) {
            return 0;
        }

        return (int) round(($this->price - $this->currentPrice()) / $this->price * 100);
    }
}

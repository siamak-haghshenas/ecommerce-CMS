<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A sellable SKU. Prices are whole Rials. Stock only changes through AdjustStockAction,
 * which records every change in stock_movements. Its picture (`image_asset_id`) is one of the
 * product's gallery files.
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
     * The price shoppers pay now: the sale price while the sale runs, else the regular price.
     */
    public function currentPrice(?CarbonInterface $at = null): int
    {
        return $this->isOnSale($at) ? (int) $this->sale_price : $this->price;
    }

    /**
     * The discount in whole percent while on sale, else 0.
     */
    public function discountPercent(?CarbonInterface $at = null): int
    {
        if (! $this->isOnSale($at) || $this->price === 0) {
            return 0;
        }

        return (int) round(($this->price - (int) $this->sale_price) / $this->price * 100);
    }
}

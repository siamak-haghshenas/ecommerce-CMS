<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One variant in a cart. `unit_price_seen` is the price the customer last saw (Rials), so a
 * change can be pointed out; `limited_from` is the quantity a merge asked for before cutting it
 * to the line's limit, kept until the customer changes the line.
 */
#[Fillable(['cart_id', 'product_variant_id', 'quantity', 'unit_price_seen', 'limited_from'])]
class CartItem extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cart_id' => 'integer',
            'product_variant_id' => 'integer',
            'quantity' => 'integer',
            'unit_price_seen' => 'integer',
            'limited_from' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Cart, $this>
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * The variant, deleted ones included (the line then shows as unavailable).
     *
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id')->withTrashed();
    }
}

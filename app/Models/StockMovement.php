<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StockMovementReason;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One change of a variant's stock. Insert-only: the history is never edited.
 */
#[Fillable([
    'product_variant_id', 'user_id', 'reason', 'quantity_change', 'quantity_before', 'quantity_after', 'note',
    'reference_type', 'reference_id',
])]
class StockMovement extends Model
{
    public const UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'product_variant_id' => 'integer',
            'user_id' => 'integer',
            'reason' => StockMovementReason::class,
            'quantity_change' => 'integer',
            'quantity_before' => 'integer',
            'quantity_after' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}

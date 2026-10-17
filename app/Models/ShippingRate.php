<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One range of a weight (grams) or volume (cubic centimetres) shipping method and its fee.
 * `range_start` is included, `range_end` excluded; a null end has no upper limit.
 */
#[Fillable(['shipping_method_id', 'range_start', 'range_end', 'fee'])]
class ShippingRate extends Model
{
    public $timestamps = false;

    /**
     * @return BelongsTo<ShippingMethod, $this>
     */
    public function method(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class, 'shipping_method_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'range_start' => 'integer',
            'range_end' => 'integer',
            'fee' => 'integer',
        ];
    }
}

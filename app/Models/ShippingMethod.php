<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ShippingMethodType;
use Database\Factories\ShippingMethodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A way of delivering orders that customers choose at checkout, priced by one `type`: weight
 * ranges, volume ranges or chosen courier cities (see App\Support\Pricing\ShippingCalculator).
 * `position` is the order customers see; `fee_on_delivery` means the carrier collects the fee.
 * Deleting is a soft delete: orders will keep pointing at the method.
 *
 * @property ShippingMethodType $type
 */
#[Fillable([
    'name', 'description', 'type', 'fee_on_delivery', 'default_fee', 'packaging_weight_grams', 'min_order_amount',
    'free_over_amount', 'delivery_days_min', 'delivery_days_max', 'is_active', 'position', 'created_by', 'updated_by',
])]
class ShippingMethod extends Model
{
    /** @use HasFactory<ShippingMethodFactory> */
    use HasFactory, SoftDeletes;

    /** At most this many ranges per weight or volume method. */
    public const int MAX_RATES = 50;

    /** At most this many cities per courier method. */
    public const int MAX_CITIES = 500;

    /**
     * @return HasMany<ShippingRate, $this>
     */
    public function rates(): HasMany
    {
        return $this->hasMany(ShippingRate::class)->orderBy('range_start');
    }

    /**
     * Courier cities; `pivot.fee` null means the default fee.
     *
     * @return BelongsToMany<City, $this>
     */
    public function cities(): BelongsToMany
    {
        return $this->belongsToMany(City::class)->withPivot('fee');
    }

    /**
     * In the order customers see them.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ShippingMethodType::class,
            'fee_on_delivery' => 'boolean',
            'default_fee' => 'integer',
            'packaging_weight_grams' => 'integer',
            'min_order_amount' => 'integer',
            'free_over_amount' => 'integer',
            'delivery_days_min' => 'integer',
            'delivery_days_max' => 'integer',
            'is_active' => 'boolean',
            'position' => 'integer',
        ];
    }
}

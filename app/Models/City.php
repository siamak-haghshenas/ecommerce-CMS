<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A city of a province (fixed data, IranLocationsSeeder).
 */
#[Fillable(['province_id', 'name'])]
class City extends Model
{
    /** @use HasFactory<CityFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @return BelongsTo<Province, $this>
     */
    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    /**
     * A courier method's own fee for this city, when loaded through ShippingMethod::cities();
     * null: the method's default fee.
     */
    public function shippingFee(): ?int
    {
        $fee = $this->getRelationValue('pivot')?->getAttribute('fee');

        return $fee === null ? null : (int) $fee;
    }
}

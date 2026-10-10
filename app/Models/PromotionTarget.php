<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PromotionTargetType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A product, category (sub-categories included), brand or tag a promotion applies to, or, when
 * `is_excluded`, a product it skips.
 */
#[Fillable(['promotion_id', 'target_type', 'target_id', 'is_excluded'])]
class PromotionTarget extends Model
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
            'promotion_id' => 'integer',
            'target_type' => PromotionTargetType::class,
            'target_id' => 'integer',
            'is_excluded' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Promotion, $this>
     */
    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }
}

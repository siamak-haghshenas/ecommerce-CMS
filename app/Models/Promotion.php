<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PromotionScope;
use App\Enums\PromotionType;
use Carbon\CarbonInterface;
use Database\Factories\PromotionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An automatic catalog discount: a percentage or a fixed amount off the regular price of the
 * products it targets, during its time window. A variant's price is the lowest of its regular
 * price, its sale price and every promotion that applies (see App\Support\Pricing\Pricing).
 */
#[Fillable(['name', 'type', 'value', 'max_discount', 'scope', 'starts_at', 'ends_at', 'is_active', 'created_by', 'updated_by'])]
class Promotion extends Model
{
    /** @use HasFactory<PromotionFactory> */
    use HasFactory;

    public const int MAX_PERCENT = 90;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PromotionType::class,
            'scope' => PromotionScope::class,
            'value' => 'integer',
            'max_discount' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
            'created_by' => 'integer',
            'updated_by' => 'integer',
        ];
    }

    /**
     * @return HasMany<PromotionTarget, $this>
     */
    public function targets(): HasMany
    {
        return $this->hasMany(PromotionTarget::class);
    }

    /**
     * "disabled", "scheduled", "active" or "expired".
     */
    public function state(?CarbonInterface $at = null): string
    {
        $at ??= now();

        return match (true) {
            ! $this->is_active => 'disabled',
            $this->starts_at !== null && $this->starts_at->gt($at) => 'scheduled',
            $this->ends_at !== null && $this->ends_at->lte($at) => 'expired',
            default => 'active',
        };
    }

    /**
     * Promotions that apply now or may apply later (enabled, not ended): the ones prices depend on.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function relevant(Builder $query): void
    {
        $query->where('is_active', true)->where(fn (Builder $end) => $end->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    /**
     * The admin list's status filter.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function inState(Builder $query, string $state): void
    {
        $now = now();

        match ($state) {
            'disabled' => $query->where('is_active', false),
            'scheduled' => $query->where('is_active', true)->where('starts_at', '>', $now),
            'expired' => $query->where('is_active', true)->where('ends_at', '<=', $now),
            default => $query->where('is_active', true)
                ->where(fn (Builder $start) => $start->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
                ->where(fn (Builder $end) => $end->whereNull('ends_at')->orWhere('ends_at', '>', $now)),
        };
    }
}

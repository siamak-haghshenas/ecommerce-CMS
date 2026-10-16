<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CouponAppliesTo;
use App\Enums\CouponCustomerScope;
use App\Enums\CouponType;
use Carbon\CarbonInterface;
use Database\Factories\CouponFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A discount code a customer enters at checkout (one per order). Its eligible items live in
 * `coupon_targets` (`applies_to` `selected`, excluded products). With `customer_scope`
 * `selected`, only the customers linked in `coupon_customer` may use it. A deleted coupon's
 * code stays taken.
 */
#[Fillable([
    'code', 'name', 'type', 'value', 'max_discount', 'applies_to', 'min_order_amount', 'usage_limit',
    'per_customer_limit', 'first_order_only', 'exclude_discounted', 'customer_scope', 'starts_at', 'ends_at',
    'is_active', 'created_by', 'updated_by',
])]
class Coupon extends Model
{
    /** @use HasFactory<CouponFactory> */
    use HasFactory, SoftDeletes;

    /** Like promotions and sale events. */
    public const int MAX_PERCENT = 90;

    public const int CODE_MIN = 3;

    public const int CODE_MAX = 50;

    /** The most customers one coupon can be limited to. */
    public const int MAX_CUSTOMERS = 10_000;

    /** The most categories, brands, tags or products in each of its lists. */
    public const int MAX_TARGETS = 1_000;

    /** @var list<string> */
    public const array STATES = ['active', 'scheduled', 'used_up', 'expired', 'disabled'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CouponType::class,
            'value' => 'integer',
            'max_discount' => 'integer',
            'applies_to' => CouponAppliesTo::class,
            'min_order_amount' => 'integer',
            'usage_limit' => 'integer',
            'per_customer_limit' => 'integer',
            'used_count' => 'integer',
            'first_order_only' => 'boolean',
            'exclude_discounted' => 'boolean',
            'customer_scope' => CouponCustomerScope::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
            'created_by' => 'integer',
            'updated_by' => 'integer',
        ];
    }

    /**
     * The customers a `selected` coupon is for (empty for `all`).
     *
     * @return BelongsToMany<User, $this>
     */
    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'coupon_customer');
    }

    /**
     * Categories whose products (sub-categories included) an `applies_to` `selected` coupon is for.
     *
     * @return MorphToMany<Category, $this>
     */
    public function categories(): MorphToMany
    {
        return $this->targets(Category::class);
    }

    /**
     * @return MorphToMany<Brand, $this>
     */
    public function brands(): MorphToMany
    {
        return $this->targets(Brand::class);
    }

    /**
     * @return MorphToMany<Tag, $this>
     */
    public function tags(): MorphToMany
    {
        return $this->targets(Tag::class);
    }

    /**
     * Products chosen themselves (`applies_to` `selected`).
     *
     * @return MorphToMany<Product, $this>
     */
    public function products(): MorphToMany
    {
        return $this->targets(Product::class);
    }

    /**
     * Products the coupon never applies to, whatever `applies_to` says.
     *
     * @return MorphToMany<Product, $this>
     */
    public function excludedProducts(): MorphToMany
    {
        return $this->targets(Product::class, excluded: true);
    }

    /**
     * @template TTarget of Model
     *
     * @param  class-string<TTarget>  $model
     * @return MorphToMany<TTarget, $this>
     */
    private function targets(string $model, bool $excluded = false): MorphToMany
    {
        return $this->morphedByMany($model, 'target', 'coupon_targets')->withPivotValue('is_excluded', $excluded);
    }

    /**
     * Whether this customer may use the code (dates, limits and the cart are checked elsewhere).
     */
    public function isFor(User $customer): bool
    {
        return $this->customer_scope === CouponCustomerScope::All
            || $this->customers()->whereKey($customer->getKey())->exists();
    }

    /**
     * disabled (switched off) › expired (ended) › used_up (usage limit reached) › scheduled › active.
     */
    public function state(?CarbonInterface $at = null): string
    {
        $at ??= now();

        return match (true) {
            ! $this->is_active => 'disabled',
            $this->ends_at !== null && $this->ends_at->lte($at) => 'expired',
            $this->usage_limit !== null && $this->used_count >= $this->usage_limit => 'used_up',
            $this->starts_at !== null && $this->starts_at->gt($at) => 'scheduled',
            default => 'active',
        };
    }

    /**
     * The admin search: the code or the name contains the text (null: every coupon).
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function matching(Builder $query, ?string $search): void
    {
        if ($search === null) {
            return;
        }

        $query->where(fn (Builder $match) => $match
            ->where('code', 'like', '%'.mb_strtoupper($search).'%')
            ->orWhere('name', 'like', '%'.$search.'%'));
    }

    /**
     * The coupons whose state() is $state, in SQL.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function inState(Builder $query, string $state): void
    {
        $now = now();
        $notExpired = fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $now);
        $usedUp = fn (Builder $q) => $q->whereNotNull('usage_limit')->whereColumn('used_count', '>=', 'usage_limit');

        match ($state) {
            'disabled' => $query->where('is_active', false),
            'expired' => $query->where('is_active', true)->where('ends_at', '<=', $now),
            'used_up' => $query->where('is_active', true)->where($notExpired)->where($usedUp),
            'scheduled' => $query->where('is_active', true)->where($notExpired)->whereNot($usedUp)->where('starts_at', '>', $now),
            default => $query->where('is_active', true)->where($notExpired)->whereNot($usedUp)
                ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now)),
        };
    }
}

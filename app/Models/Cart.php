<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CartFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A shopping cart: a customer's (`user_id`, one per account) or a guest's (`token_hash`, the
 * sha256 of the token the storefront sends in `X-Cart-Token`). Lines hold only the variant and
 * quantity; prices, stock and limits are worked out on every read (SummarizeCartAction).
 */
#[Fillable(['user_id', 'token_hash', 'coupon_code', 'last_activity_at'])]
class Cart extends Model
{
    /** @use HasFactory<CartFactory> */
    use HasFactory, MassPrunable;

    /** The request header a guest cart's token comes in. */
    public const string TOKEN_HEADER = 'X-Cart-Token';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'last_activity_at' => 'datetime',
        ];
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Lines in the order they were added.
     *
     * @return HasMany<CartItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class)->orderBy('id');
    }

    public function isGuest(): bool
    {
        return $this->user_id === null;
    }

    /**
     * Guest and customer carts nobody touched for a while.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where(fn (Builder $query) => $query
            ->where(fn (Builder $guest) => $guest
                ->whereNull('user_id')
                ->where('last_activity_at', '<', now()->minus(days: (int) config('cart.prune_after_days.guest'))))
            ->orWhere(fn (Builder $customer) => $customer
                ->whereNotNull('user_id')
                ->where('last_activity_at', '<', now()->minus(days: (int) config('cart.prune_after_days.customer')))));
    }
}

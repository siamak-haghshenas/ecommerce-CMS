<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Cart;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Cart>
 */
class CartFactory extends Factory
{
    /**
     * A guest cart (its token is not kept; use `withToken()` to know it).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'token_hash' => Cart::hashToken(Str::random(40)),
            'coupon_code' => null,
            'last_activity_at' => now(),
        ];
    }

    /**
     * A guest cart opened with this token.
     */
    public function withToken(string $token): static
    {
        return $this->state(['user_id' => null, 'token_hash' => Cart::hashToken($token)]);
    }
}

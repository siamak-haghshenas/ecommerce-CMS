<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OtpPurpose;
use App\Models\OtpCode;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<OtpCode>
 */
class OtpCodeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'phone_number' => '09'.fake()->unique()->numerify('#########'),
            'purpose' => OtpPurpose::Login,
            'code_hash' => Hash::make('12345'),
            'expires_at' => now()->plus(seconds: (int) config('otp.ttl_seconds')),
        ];
    }

    /**
     * Indicate that the code has expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->minus(seconds: 1),
        ]);
    }
}

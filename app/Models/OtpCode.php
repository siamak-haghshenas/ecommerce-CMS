<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OtpPurpose;
use Database\Factories\OtpCodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['phone_number', 'purpose', 'code_hash', 'expires_at', 'ip_address'])]
#[Hidden(['code_hash'])]
class OtpCode extends Model
{
    /** @use HasFactory<OtpCodeFactory> */
    use HasFactory, MassPrunable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purpose' => OtpPurpose::class,
            'attempts' => 'integer',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Codes for the given phone number and purpose that have not been used yet.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function unconsumed(Builder $query, string $phoneNumber, OtpPurpose $purpose): void
    {
        $query->where('phone_number', $phoneNumber)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at');
    }

    /**
     * Codes that expired more than a day ago.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('expires_at', '<=', now()->minus(days: 1));
    }
}

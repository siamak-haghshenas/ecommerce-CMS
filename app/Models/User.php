<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Permission;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * Every user has exactly one role: a customer role or one staff role.
 */
#[Fillable(['name', 'phone_number', 'email', 'password', 'locale'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, Prunable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'anonymised_at' => 'datetime',
        ];
    }

    public function hasVerifiedPhoneNumber(): bool
    {
        return $this->phone_verified_at !== null;
    }

    public function isAnonymised(): bool
    {
        return $this->anonymised_at !== null;
    }

    public function isStaff(): bool
    {
        return $this->hasRole(UserRole::staffRoleValues());
    }

    /**
     * The user's single role (see the class docblock).
     */
    public function primaryRole(): ?UserRole
    {
        $name = $this->getRoleNames()->first();

        return is_string($name) ? UserRole::tryFrom($name) : null;
    }

    public function canAccessAdminPanel(): bool
    {
        return $this->can(Permission::AccessAdminPanel->value);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function customers(Builder $query): void
    {
        $query->role(UserRole::Customer->value);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function staff(Builder $query): void
    {
        $query->role(UserRole::staffRoleValues());
    }

    /**
     * Registrations whose phone number was never verified.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()
            ->whereNull('phone_verified_at')
            ->where('updated_at', '<=', now()->minus(hours: (int) config('otp.unverified_user_ttl_hours')));
    }
}

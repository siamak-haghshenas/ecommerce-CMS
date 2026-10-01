<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Admin;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class CustomerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->isAnonymised() ? __('admin.anonymised_name') : $this->name,
            'phone_number' => $this->phone_number,
            'email' => $this->email,
            'locale' => $this->locale,
            'is_active' => $this->is_active,
            'is_anonymised' => $this->isAnonymised(),
            'registration_completed' => $this->hasVerifiedPhoneNumber(),
            'phone_verified_at' => $this->phone_verified_at?->toIso8601String(),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'anonymised_at' => $this->anonymised_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

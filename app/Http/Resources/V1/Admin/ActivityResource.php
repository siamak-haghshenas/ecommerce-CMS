<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Admin;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Activitylog\Models\Activity;

/**
 * One audit-log entry. Properties never contain personal data (see the account actions).
 *
 * @mixin Activity
 */
class ActivityResource extends JsonResource
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
            'event' => $this->event,
            'properties' => $this->properties,
            'causer' => $this->userSummary($this->causer),
            'subject' => $this->userSummary($this->subject),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{id: int, name: string, is_anonymised: bool}|null
     */
    private function userSummary(mixed $user): ?array
    {
        if (! $user instanceof User) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->isAnonymised() ? (string) __('admin.anonymised_name') : $user->name,
            'is_anonymised' => $user->isAnonymised(),
        ];
    }
}

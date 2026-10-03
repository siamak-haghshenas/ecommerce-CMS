<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Admin;

use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Option;
use App\Models\Product;
use App\Models\Tag;
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
            'causer' => $this->summary($this->causer),
            'subject' => $this->summary($this->subject),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * A user or category, or null when there is none (or it has been deleted).
     *
     * @return array{type: 'user'|'category'|'brand'|'attribute'|'option'|'product'|'tag', id: int, name: string, is_anonymised: bool}|null
     */
    private function summary(mixed $model): ?array
    {
        if ($model instanceof Category) {
            return ['type' => 'category', 'id' => $model->id, 'name' => $model->name, 'is_anonymised' => false];
        }

        if ($model instanceof Brand) {
            return ['type' => 'brand', 'id' => $model->id, 'name' => $model->name, 'is_anonymised' => false];
        }

        if ($model instanceof Attribute || $model instanceof Option || $model instanceof Product || $model instanceof Tag) {
            $type = match (true) {
                $model instanceof Attribute => 'attribute',
                $model instanceof Option => 'option',
                $model instanceof Product => 'product',
                default => 'tag',
            };

            return ['type' => $type, 'id' => $model->id, 'name' => $model->name, 'is_anonymised' => false];
        }

        if (! $model instanceof User) {
            return null;
        }

        return [
            'type' => 'user',
            'id' => $model->id,
            'name' => $model->isAnonymised() ? (string) __('admin.anonymised_name') : $model->name,
            'is_anonymised' => $model->isAnonymised(),
        ];
    }
}

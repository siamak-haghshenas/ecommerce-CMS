<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Admin;

use App\Models\Attribute;
use App\Models\AttributeValue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Attribute
 */
class AttributeResource extends JsonResource
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
            'name' => $this->name,
            'slug' => $this->slug,
            'type' => $this->type->value,
            'unit' => $this->unit,
            'is_filterable' => $this->is_filterable,
            'position' => $this->position,
            'values' => $this->whenLoaded('values', fn () => $this->values->map(fn (AttributeValue $value): array => [
                'id' => $value->id,
                'value' => $value->value,
                'slug' => $value->slug,
            ])->all()),
            // How many categories use it directly (not counting inheritance).
            'categories_count' => $this->whenCounted('categories'),
        ];
    }
}

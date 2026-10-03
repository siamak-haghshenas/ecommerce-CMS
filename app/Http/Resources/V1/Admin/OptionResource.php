<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Admin;

use App\Models\Option;
use App\Models\OptionValue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Option
 */
class OptionResource extends JsonResource
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
            'display_type' => $this->display_type->value,
            'position' => $this->position,
            'values' => $this->whenLoaded('values', fn () => $this->values->map(fn (OptionValue $value): array => [
                'id' => $value->id,
                'value' => $value->value,
                'slug' => $value->slug,
                'color_hex' => $value->color_hex,
            ])->all()),
        ];
    }
}

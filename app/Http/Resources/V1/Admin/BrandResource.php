<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Admin;

use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Brand
 */
class BrandResource extends JsonResource
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
            'persian_name' => $this->persian_name,
            'slug' => $this->slug,
            'description' => $this->description,
            'website' => $this->website,
            'position' => $this->position,
            'is_active' => $this->is_active,
            'logo' => $this->logoUrls(),
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'focus_keyphrase' => $this->focus_keyphrase,
            'seo_score' => $this->seo_score,
            'readability_score' => $this->readability_score,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

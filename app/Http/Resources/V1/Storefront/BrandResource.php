<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Storefront;

use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A visible brand, with what a storefront needs for its brand page. Never the focus keyphrase or scores.
 *
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
            'logo' => $this->logoUrls(),
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
        ];
    }
}

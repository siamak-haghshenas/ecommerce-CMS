<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\City;
use App\Models\Province;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A province with its cities, for address forms and courier city pickers.
 *
 * @mixin Province
 */
class ProvinceResource extends JsonResource
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
            'cities' => $this->cities->map(fn (City $city): array => ['id' => $city->id, 'name' => $city->name])->values()->all(),
        ];
    }
}

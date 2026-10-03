<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Admin;

use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StockMovement
 */
class StockMovementResource extends JsonResource
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
            'variant' => ['id' => $this->product_variant_id, 'sku' => $this->variant?->sku],
            'user' => $this->user === null ? null : ['id' => $this->user->id, 'name' => $this->user->name],
            'reason' => $this->reason->value,
            'quantity_change' => $this->quantity_change,
            'quantity_before' => $this->quantity_before,
            'quantity_after' => $this->quantity_after,
            'note' => $this->note,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

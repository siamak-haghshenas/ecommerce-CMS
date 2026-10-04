<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Admin;

use App\Models\MediaUpload;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An upload in progress: send `chunk_size`-byte chunks starting at `received`.
 *
 * @mixin MediaUpload
 */
class MediaUploadResource extends JsonResource
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
            'size' => $this->size,
            'received' => $this->received,
            'chunk_size' => (int) config('media.chunk_kb') * 1024,
            'expires_at' => $this->expires_at->toIso8601String(),
        ];
    }
}

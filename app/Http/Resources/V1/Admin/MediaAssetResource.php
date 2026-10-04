<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Admin;

use App\Models\Brand;
use App\Models\Category;
use App\Models\MediaAsset;
use App\Models\MediaUsage;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A file of the media library. `usages` ("where it's used") is included when loaded (the details view).
 *
 * @mixin MediaAsset
 */
class MediaAssetResource extends JsonResource
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
            'type' => $this->type->value,
            'name' => $this->name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'width' => $this->width,
            'height' => $this->height,
            'title' => $this->title,
            'alt' => $this->alt,
            'urls' => $this->urls(),
            'folder' => $this->folder === null ? null : ['id' => $this->folder->id, 'name' => $this->folder->name],
            'cover' => $this->cover === null ? null : ['id' => $this->cover->id, 'thumb' => $this->cover->urls()['thumb'] ?? null],
            'in_use' => $this->whenCounted('usages', fn (): bool => $this->usages_count + (int) ($this->covered_videos_count ?? 0) > 0),
            'usages' => $this->whenLoaded('usages', fn (): array => $this->usages
                ->map(fn (MediaUsage $usage): ?array => $this->usage($usage))
                ->filter()
                ->values()
                ->all()),
            'uploaded_by' => $this->whenLoaded('uploader', fn (): ?array => $this->uploader === null ? null : [
                'id' => $this->uploader->id,
                'name' => $this->uploader->name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{type: string, id: int, name: string, role: string}|null
     */
    private function usage(MediaUsage $usage): ?array
    {
        $item = $usage->usable;
        $type = match (true) {
            $item instanceof Product => 'product',
            $item instanceof Category => 'category',
            $item instanceof Brand => 'brand',
            default => null,
        };

        return $type === null ? null : ['type' => $type, 'id' => (int) $item->getKey(), 'name' => (string) $item->getAttribute('name'), 'role' => $usage->role->value];
    }

    /**
     * The relations of the details view.
     *
     * @return list<string>
     */
    public static function detailRelations(): array
    {
        return ['media', 'folder', 'cover.media', 'usages.usable', 'uploader'];
    }
}

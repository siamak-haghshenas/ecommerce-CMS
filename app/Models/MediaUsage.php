<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MediaUsageRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One place a library file is used: an item (product, category, brand, ...) and the role it plays there.
 */
#[Fillable(['media_asset_id', 'usable_type', 'usable_id', 'role', 'position'])]
class MediaUsage extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'media_asset_id' => 'integer',
            'usable_id' => 'integer',
            'role' => MediaUsageRole::class,
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'media_asset_id')->withTrashed();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function usable(): MorphTo
    {
        return $this->morphTo();
    }
}

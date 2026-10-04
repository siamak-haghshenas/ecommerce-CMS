<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A chunked upload in progress. Chunks are appended to a temporary file on the local disk until
 * `received` reaches `size`; unfinished uploads are pruned after config media.upload_expiry_hours.
 */
#[Fillable(['user_id', 'name', 'mime_type', 'size', 'received', 'folder_id', 'expires_at'])]
class MediaUpload extends Model
{
    use HasUuids, Prunable;

    private const string DIRECTORY = 'media-uploads';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'size' => 'integer',
            'received' => 'integer',
            'folder_id' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The temporary file's path on the local disk.
     */
    public function path(): string
    {
        return self::DIRECTORY.'/'.$this->id.'.part';
    }

    public function isComplete(): bool
    {
        return $this->received === $this->size;
    }

    /**
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return self::query()->where('expires_at', '<=', now());
    }

    protected function pruning(): void
    {
        Storage::disk('local')->delete($this->path());
    }
}

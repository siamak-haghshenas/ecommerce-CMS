<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CommentableType;
use App\Enums\CommentStatus;
use Database\Factories\CommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A customer's comment on a blog post or a product (`commentable`, see CommentableType). It
 * starts pending and is public only once approved. The customer account is linked for moderation
 * only; storefronts get the display name and the text. Staff may add one reply, shown under the
 * comment as the store's answer.
 */
#[Fillable(['user_id', 'display_name', 'body'])]
class Comment extends Model
{
    /** @use HasFactory<CommentFactory> */
    use HasFactory;

    public const int BODY_MAX = 2000;

    public const int DISPLAY_NAME_MAX = 60;

    public const int REASON_MAX = 500;

    /** The most comments one bulk action takes. */
    public const int BULK_MAX = 100;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'commentable_id' => 'integer',
            'user_id' => 'integer',
            'status' => CommentStatus::class,
            'moderated_by' => 'integer',
            'moderated_at' => 'datetime',
            'replied_by' => 'integer',
            'replied_at' => 'datetime',
        ];
    }

    /**
     * The post or product, deleted ones included (their comments stay until they are removed for good).
     *
     * @return MorphTo<Model, $this>
     */
    public function commentable(): MorphTo
    {
        return $this->morphTo()->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function replier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'replied_by');
    }

    public function commentableType(): ?CommentableType
    {
        return CommentableType::fromClass((string) $this->commentable_type);
    }

    /**
     * What the audit log keeps of the commented item: its kind and id (no text, no names).
     *
     * @return array{type: string|null, item_id: int}
     */
    public function auditItem(): array
    {
        return ['type' => $this->commentableType()?->value, 'item_id' => $this->commentable_id];
    }

    /**
     * The comments storefronts show.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function approved(Builder $query): void
    {
        $query->where('status', CommentStatus::Approved);
    }

    /**
     * The admin search: the text or the display name contains $search, on one kind of item or one
     * item ($itemId needs $type).
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function matching(Builder $query, ?string $search, ?CommentableType $type = null, ?int $itemId = null): void
    {
        $like = '%'.addcslashes((string) $search, '%_\\').'%';

        $query->when($type !== null, fn (Builder $query) => $query->where('commentable_type', $type?->modelClass()))
            ->when($type !== null && $itemId !== null, fn (Builder $query) => $query->where('commentable_id', $itemId))
            ->when($search !== null, fn (Builder $query) => $query->where(fn (Builder $match) => $match
                ->where('body', 'like', $like)
                ->orWhere('display_name', 'like', $like)));
    }
}

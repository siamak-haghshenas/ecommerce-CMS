<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Comment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Something customers comment on (a blog post or a product), switched on and off with its
 * `comments_enabled` column. Soft-deletable: removed for good, it takes its comments; in the trash it keeps
 * them (it can be restored).
 *
 * @mixin Model
 */
trait HasComments
{
    public static function bootHasComments(): void
    {
        static::forceDeleted(function (self $model): void {
            $model->comments()->delete();
        });
    }

    /**
     * Customers' comments, every status (`approved()` for the public ones).
     *
     * @return MorphMany<Comment, $this>
     */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    /**
     * Whether customers may post new comments (existing approved ones stay visible).
     */
    public function acceptsComments(): bool
    {
        return $this->getAttribute('comments_enabled') !== false;
    }

    /**
     * The name the moderation list shows.
     */
    public function commentableTitle(): string
    {
        return (string) ($this->getAttribute('title') ?? $this->getAttribute('name'));
    }
}

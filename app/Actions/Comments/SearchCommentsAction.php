<?php

declare(strict_types=1);

namespace App\Actions\Comments;

use App\Data\Comments\CommentFilters;
use App\Models\Comment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class SearchCommentsAction
{
    /**
     * The moderation list, newest first, with each comment's post or product and who moderated and replied.
     *
     * @return LengthAwarePaginator<int, Comment>
     */
    public function handle(CommentFilters $filters): LengthAwarePaginator
    {
        return Comment::query()
            ->matching($filters->search, $filters->type, $filters->itemId)
            ->when($filters->status !== null, fn (Builder $query) => $query->where('status', $filters->status))
            ->with(['commentable', 'moderator:id,name', 'replier:id,name'])
            ->latest('id')
            ->paginate($filters->perPage);
    }
}

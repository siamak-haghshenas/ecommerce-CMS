<?php

declare(strict_types=1);

namespace App\Actions\Posts;

use App\Data\Posts\PostFilters;
use App\Models\Post;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class SearchPostsAction
{
    /**
     * The admin post list.
     *
     * @return LengthAwarePaginator<int, Post>
     */
    public function handle(PostFilters $filters): LengthAwarePaginator
    {
        $query = Post::query()->with(['editor', 'author', 'categories', 'imageAssets.media'])->matching($filters);

        match ($filters->sort) {
            'title' => $query->orderBy('title'),
            'newest' => $query->latest('id'),
            default => $query->latest('updated_at')->latest('id'),
        };

        return $query->paginate($filters->perPage);
    }
}

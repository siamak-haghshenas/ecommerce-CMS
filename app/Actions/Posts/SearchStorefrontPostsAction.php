<?php

declare(strict_types=1);

namespace App\Actions\Posts;

use App\Data\Posts\StorefrontPostFilters;
use App\Enums\CategoryType;
use App\Models\Post;
use App\Support\CategoryTree;
use App\Support\Seo\SeoText;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class SearchStorefrontPostsAction
{
    /**
     * Published posts, newest first. An unknown or hidden category, tag or author matches nothing
     * (never silently ignored).
     *
     * @param  list<string>  $with
     * @return LengthAwarePaginator<int, Post>
     */
    public function handle(StorefrontPostFilters $filters, array $with): LengthAwarePaginator
    {
        $query = Post::query()->visible()->with($with);

        if ($filters->category !== null) {
            $tree = CategoryTree::current();
            $node = $tree->findBySlug($filters->category, CategoryType::Blog);
            $ids = $node === null || ! $tree->isVisible($node->id)
                ? [0]
                : array_values(array_intersect([$node->id, ...$tree->descendantIds($node->id)], $tree->visibleIds(CategoryType::Blog)));
            $query->whereHas('categories', fn (Builder $categories) => $categories->whereKey($ids));
        }

        if ($filters->tag !== null) {
            $query->whereHas('tags', fn (Builder $tags) => $tags->where('slug', $filters->tag));
        }

        if ($filters->author !== null) {
            $query->whereHas('author', fn (Builder $author) => $author->where('author_slug', $filters->author)->whereNotNull('author_name'));
        }

        // Every word must appear in the title or the excerpt.
        foreach (SeoText::words((string) $filters->search) as $word) {
            $query->where(fn (Builder $match) => $match->where('title', 'like', "%{$word}%")->orWhere('excerpt', 'like', "%{$word}%"));
        }

        return $query->orderByDesc('published_at')->orderByDesc('id')->paginate($filters->perPage);
    }
}

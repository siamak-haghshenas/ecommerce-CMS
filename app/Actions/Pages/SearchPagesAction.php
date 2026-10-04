<?php

declare(strict_types=1);

namespace App\Actions\Pages;

use App\Data\Pages\PageFilters;
use App\Models\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class SearchPagesAction
{
    /**
     * The admin page list.
     *
     * @return LengthAwarePaginator<int, Page>
     */
    public function handle(PageFilters $filters): LengthAwarePaginator
    {
        $query = Page::query()->with(['editor', 'imageAssets.media'])->matching($filters);

        match ($filters->sort) {
            'title' => $query->orderBy('title'),
            'newest' => $query->latest('id'),
            default => $query->latest('updated_at')->latest('id'),
        };

        return $query->paginate($filters->perPage);
    }
}

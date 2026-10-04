<?php

declare(strict_types=1);

namespace App\Actions\Media;

use App\Data\Media\MediaFilters;
use App\Models\MediaAsset;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class SearchMediaAction
{
    /**
     * The media library grid.
     *
     * @return LengthAwarePaginator<int, MediaAsset>
     */
    public function handle(MediaFilters $filters): LengthAwarePaginator
    {
        $query = MediaAsset::query()
            ->with(['media', 'folder', 'cover.media'])
            ->withCount(['usages', 'coveredVideos'])
            ->when($filters->trashed, fn (Builder $query) => $query->onlyTrashed())
            ->when($filters->type, fn (Builder $query, $type) => $query->where('type', $type))
            ->when($filters->folder === 'none', fn (Builder $query) => $query->whereNull('folder_id'))
            ->when(is_int($filters->folder), fn (Builder $query) => $query->where('folder_id', $filters->folder))
            ->when($filters->used !== null, fn (Builder $query) => $query->used((bool) $filters->used));

        $term = trim((string) $filters->search);

        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(fn (Builder $search) => $search
                ->where('name', 'like', $like)
                ->orWhere('title', 'like', $like)
                ->orWhere('alt', 'like', $like));
        }

        match ($filters->sort) {
            'oldest' => $query->oldest('id'),
            'name' => $query->orderBy('name'),
            'size' => $query->orderByDesc('size'),
            default => $query->latest('id'),
        };

        return $query->paginate($filters->perPage);
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Promotions;

use App\Data\Promotions\PromotionFilters;
use App\Models\Promotion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class SearchPromotionsAction
{
    /**
     * The admin promotion list, newest first.
     *
     * @return LengthAwarePaginator<int, Promotion>
     */
    public function handle(PromotionFilters $filters): LengthAwarePaginator
    {
        return Promotion::query()
            ->with('targets')
            ->when($filters->search !== null, fn (Builder $query) => $query->where('name', 'like', '%'.$filters->search.'%'))
            ->when($filters->state !== null, fn (Builder $query) => $query->inState((string) $filters->state))
            ->latest('id')
            ->paginate($filters->perPage);
    }
}

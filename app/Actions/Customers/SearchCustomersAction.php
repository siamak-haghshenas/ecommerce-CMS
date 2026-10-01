<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\Data\Admin\CustomerFilters;
use App\Models\User;
use App\Support\Digits;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class SearchCustomersAction
{
    /**
     * Customers matching the filters, newest first.
     *
     * @return LengthAwarePaginator<int, User>
     */
    public function handle(CustomerFilters $filters): LengthAwarePaginator
    {
        return User::query()
            ->customers()
            ->when($filters->search, function (Builder $query, string $search): void {
                $term = '%'.addcslashes(Digits::toLatin(trim($search)), '%_\\').'%';

                $query->where(fn (Builder $match) => $match
                    ->where('name', 'like', $term)
                    ->orWhere('phone_number', 'like', $term)
                    ->orWhere('email', 'like', $term));
            })
            ->when($filters->status === 'active', fn (Builder $query) => $query->where('is_active', true)->whereNull('anonymised_at'))
            ->when($filters->status === 'inactive', fn (Builder $query) => $query->where('is_active', false)->whereNull('anonymised_at'))
            ->when($filters->status === 'anonymised', fn (Builder $query) => $query->whereNotNull('anonymised_at'))
            ->when($filters->registration === 'completed', fn (Builder $query) => $query->whereNotNull('phone_verified_at'))
            ->when($filters->registration === 'pending', fn (Builder $query) => $query->whereNull('phone_verified_at'))
            ->latest('id')
            ->paginate($filters->perPage);
    }
}

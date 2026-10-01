<?php

declare(strict_types=1);

namespace App\Actions\Staff;

use App\Data\Admin\StaffFilters;
use App\Models\User;
use App\Support\Digits;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class SearchStaffAction
{
    /**
     * Staff members matching the filters, alphabetically.
     *
     * @return LengthAwarePaginator<int, User>
     */
    public function handle(StaffFilters $filters): LengthAwarePaginator
    {
        return User::query()
            ->with('roles')
            ->when(
                $filters->role,
                fn (Builder $query, $role) => $query->role($role->value),
                fn (Builder $query) => $query->staff(),
            )
            ->when($filters->search, function (Builder $query, string $search): void {
                $term = '%'.addcslashes(Digits::toLatin(trim($search)), '%_\\').'%';

                $query->where(fn (Builder $match) => $match
                    ->where('name', 'like', $term)
                    ->orWhere('phone_number', 'like', $term)
                    ->orWhere('email', 'like', $term));
            })
            ->when($filters->status === 'active', fn (Builder $query) => $query->where('is_active', true))
            ->when($filters->status === 'inactive', fn (Builder $query) => $query->where('is_active', false))
            ->orderBy('name')
            ->paginate($filters->perPage);
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

final class SearchActivityLogAction
{
    /**
     * Audit-log entries of admin actions, newest first.
     *
     * @return LengthAwarePaginator<int, Activity>
     */
    public function handle(?int $subjectUserId, ?int $causerUserId, ?string $event, int $perPage): LengthAwarePaginator
    {
        return Activity::query()
            ->inLog('admin')
            ->with(['causer', 'subject'])
            ->when($subjectUserId, fn (Builder $query, int $id) => $query->where('subject_type', (new User)->getMorphClass())->where('subject_id', $id))
            ->when($causerUserId, fn (Builder $query, int $id) => $query->where('causer_type', (new User)->getMorphClass())->where('causer_id', $id))
            ->when($event, fn (Builder $query, string $name) => $query->where('event', $name))
            ->latest('id')
            ->paginate($perPage);
    }
}

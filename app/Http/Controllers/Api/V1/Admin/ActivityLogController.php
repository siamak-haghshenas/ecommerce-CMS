<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Admin\SearchActivityLogAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\IndexActivityLogRequest;
use App\Http\Resources\V1\Admin\ActivityResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ActivityLogController extends Controller
{
    public function __invoke(IndexActivityLogRequest $request, SearchActivityLogAction $search): AnonymousResourceCollection
    {
        return ActivityResource::collection($search->handle(
            subjectUserId: $request->filled('subject_id') ? $request->integer('subject_id') : null,
            causerUserId: $request->filled('causer_id') ? $request->integer('causer_id') : null,
            event: $request->filled('event') ? $request->string('event')->toString() : null,
            perPage: $request->integer('per_page', 20),
        ));
    }
}

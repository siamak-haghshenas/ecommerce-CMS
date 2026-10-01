<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Admin\GetDashboardSummaryAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Admin\DashboardResource;

class DashboardController extends Controller
{
    public function __invoke(GetDashboardSummaryAction $getSummary): DashboardResource
    {
        return new DashboardResource($getSummary->handle());
    }
}

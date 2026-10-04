<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Brands;

use App\Actions\Brands\ReorderBrandsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Brands\ReorderBrandsRequest;
use App\Http\Resources\V1\Admin\BrandResource;
use App\Models\Brand;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BrandOrderController extends Controller
{
    /**
     * Save a new manual order (drag and drop, move up/down) and return the reordered list.
     */
    public function __invoke(ReorderBrandsRequest $request, ReorderBrandsAction $reorder, #[CurrentUser] User $actor): AnonymousResourceCollection
    {
        $reorder->handle($actor, $request->ids());

        return BrandResource::collection(Brand::query()->with('logoAssets.media')->ordered()->get());
    }
}

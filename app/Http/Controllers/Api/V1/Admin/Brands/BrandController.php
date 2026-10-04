<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Brands;

use App\Actions\Brands\CreateBrandAction;
use App\Actions\Brands\DeleteBrandAction;
use App\Actions\Brands\UpdateBrandAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Brands\SaveBrandRequest;
use App\Http\Resources\V1\Admin\BrandResource;
use App\Models\Brand;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class BrandController extends Controller
{
    /**
     * Every brand in manual order, hidden ones included. Not paginated: admins order the whole list.
     */
    public function index(): AnonymousResourceCollection
    {
        return BrandResource::collection(Brand::query()->with('logoAssets.media')->ordered()->get());
    }

    public function show(Brand $brand): BrandResource
    {
        return new BrandResource($brand->load('logoAssets.media'));
    }

    public function store(SaveBrandRequest $request, CreateBrandAction $create, #[CurrentUser] User $actor): BrandResource
    {
        return new BrandResource($create->handle($actor, $request->toData())->load('logoAssets.media'));
    }

    public function update(SaveBrandRequest $request, Brand $brand, UpdateBrandAction $update, #[CurrentUser] User $actor): BrandResource
    {
        return new BrandResource($update->handle($actor, $brand, $request->toData())->load('logoAssets.media'));
    }

    public function destroy(Brand $brand, DeleteBrandAction $delete, #[CurrentUser] User $actor): Response
    {
        $delete->handle($actor, $brand);

        return response()->noContent();
    }
}

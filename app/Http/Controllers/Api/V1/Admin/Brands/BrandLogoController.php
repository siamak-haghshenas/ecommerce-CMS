<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Brands;

use App\Actions\Brands\RemoveBrandLogoAction;
use App\Actions\Brands\UpdateBrandLogoAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Brands\UpdateBrandLogoRequest;
use App\Http\Resources\V1\Admin\BrandResource;
use App\Models\Brand;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;

class BrandLogoController extends Controller
{
    public function store(UpdateBrandLogoRequest $request, Brand $brand, UpdateBrandLogoAction $updateLogo, #[CurrentUser] User $actor): BrandResource
    {
        return new BrandResource($updateLogo->handle($actor, $brand, $request->logo()));
    }

    public function destroy(Brand $brand, RemoveBrandLogoAction $removeLogo, #[CurrentUser] User $actor): BrandResource
    {
        return new BrandResource($removeLogo->handle($actor, $brand));
    }
}

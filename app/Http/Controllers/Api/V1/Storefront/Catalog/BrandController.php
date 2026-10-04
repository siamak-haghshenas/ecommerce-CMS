<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Storefront\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Storefront\BrandResource;
use App\Models\Brand;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BrandController extends Controller
{
    /**
     * Visible brands in the admin's manual order.
     */
    public function index(): AnonymousResourceCollection
    {
        return BrandResource::collection(Brand::query()->with('logoAssets.media')->where('is_active', true)->ordered()->get());
    }

    /**
     * One visible brand by slug; hidden or unknown brands are 404.
     */
    public function show(string $slug): BrandResource
    {
        return new BrandResource(
            Brand::query()->with('logoAssets.media')->where('is_active', true)->where('slug', $slug)->firstOrFail(),
        );
    }
}

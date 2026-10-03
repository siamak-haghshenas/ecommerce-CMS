<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Storefront\Catalog;

use App\Actions\Catalog\BuildStorefrontFiltersAction;
use App\Actions\Catalog\SearchStorefrontProductsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Storefront\Catalog\ProductFiltersRequest;
use App\Http\Resources\V1\Storefront\ProductListResource;
use App\Http\Resources\V1\Storefront\ProductResource;
use App\Support\Catalog\StorefrontProductQuery;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    /**
     * Visible products matching the filters, paginated.
     */
    public function index(ProductFiltersRequest $request, SearchStorefrontProductsAction $search, Settings $settings): AnonymousResourceCollection
    {
        return ProductListResource::collection($search->handle($request->toFilters()))
            ->additional(['meta' => ['currency' => $this->currency($settings)]]);
    }

    /**
     * One visible product by slug; anything else (draft, scheduled, deleted, hidden) is 404.
     */
    public function show(string $slug, Settings $settings): ProductResource
    {
        $product = StorefrontProductQuery::visible()->where('slug', $slug)->with(ProductResource::relations())->firstOrFail();

        return (new ProductResource($product))->additional(['meta' => ['currency' => $this->currency($settings)]]);
    }

    /**
     * The filters to offer for the search/category/brand/tag in the query, with product counts.
     */
    public function filters(ProductFiltersRequest $request, BuildStorefrontFiltersAction $filters, Settings $settings): JsonResponse
    {
        return response()->json([
            'data' => $filters->handle($request->toFilters()),
            'meta' => ['currency' => $this->currency($settings)],
        ]);
    }

    /**
     * Amounts are Rials; this says how the store shows them (1 Toman = 10 Rials).
     *
     * @return array{display: string, rials_per_unit: int}
     */
    private function currency(Settings $settings): array
    {
        $currency = $settings->priceDisplayCurrency();

        return ['display' => $currency->value, 'rials_per_unit' => $currency->rialsPerUnit()];
    }
}

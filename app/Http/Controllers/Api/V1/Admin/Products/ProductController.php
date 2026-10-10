<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Products;

use App\Actions\Products\ChangeProductStatusAction;
use App\Actions\Products\DeleteProductAction;
use App\Actions\Products\MatchingProductIdsAction;
use App\Actions\Products\SaveProductAction;
use App\Actions\Products\SearchProductsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Products\ChangeProductStatusRequest;
use App\Http\Requests\Api\V1\Admin\Products\IndexProductsRequest;
use App\Http\Requests\Api\V1\Admin\Products\SaveProductRequest;
use App\Http\Resources\V1\Admin\ProductListResource;
use App\Http\Resources\V1\Admin\ProductResource;
use App\Models\Product;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ProductController extends Controller
{
    public function index(IndexProductsRequest $request, SearchProductsAction $search): AnonymousResourceCollection
    {
        return ProductListResource::collection($search->handle($request->toFilters()));
    }

    /**
     * The ids of every product matching the list's filters ("select all matching"), up to a limit.
     */
    public function ids(IndexProductsRequest $request, MatchingProductIdsAction $matching): JsonResponse
    {
        return response()->json(['data' => $matching->handle($request->toFilters())]);
    }

    /**
     * Also for deleted products (route binding includes them), so they can be viewed and restored.
     */
    public function show(Product $product): ProductResource
    {
        return new ProductResource($product->load(ProductResource::relations()));
    }

    public function store(SaveProductRequest $request, SaveProductAction $save, #[CurrentUser] User $actor): ProductResource
    {
        return new ProductResource($save->handle($actor, null, $request->toData())->load(ProductResource::relations()));
    }

    public function update(SaveProductRequest $request, Product $product, SaveProductAction $save, #[CurrentUser] User $actor): ProductResource
    {
        return new ProductResource($save->handle($actor, $product, $request->toData())->load(ProductResource::relations()));
    }

    public function destroy(Product $product, DeleteProductAction $delete, #[CurrentUser] User $actor): Response
    {
        $delete->handle($actor, $product);

        return response()->noContent();
    }

    public function restore(Product $product, DeleteProductAction $delete, #[CurrentUser] User $actor): ProductResource
    {
        $delete->restore($actor, $product);

        return new ProductResource($product->load(ProductResource::relations()));
    }

    /**
     * Bulk publish / archive / back to draft. Products that can't be published are skipped.
     */
    public function changeStatus(ChangeProductStatusRequest $request, ChangeProductStatusAction $change, #[CurrentUser] User $actor): JsonResponse
    {
        return response()->json(['data' => $change->handle($actor, $request->ids(), $request->status())]);
    }
}

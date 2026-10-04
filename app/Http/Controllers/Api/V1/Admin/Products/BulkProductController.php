<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Products;

use App\Actions\Products\BulkUpdateVariantsAction;
use App\Actions\Products\SearchProductsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Products\BulkUpdateVariantsRequest;
use App\Http\Requests\Api\V1\Admin\Products\IndexBulkProductsRequest;
use App\Http\Resources\V1\Admin\BulkProductResource;
use App\Models\Product;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The bulk edit page: a page of products with their variants, and one save for all changes.
 */
class BulkProductController extends Controller
{
    private const array QUICK_FILTERS = ['out', 'low', 'sale', 'inactive'];

    /**
     * `meta.quick_counts` counts each quick filter with the other filters applied.
     */
    public function index(IndexBulkProductsRequest $request, SearchProductsAction $search): AnonymousResourceCollection
    {
        $filters = $request->toFilters();
        $counts = ['all' => Product::query()->matching($filters->withQuick(null))->count()];

        foreach (self::QUICK_FILTERS as $quick) {
            $counts[$quick] = Product::query()->matching($filters->withQuick($quick))->count();
        }

        return BulkProductResource::collection($search->handle($filters, BulkProductResource::relations()))
            ->additional(['meta' => ['quick_counts' => $counts]]);
    }

    /**
     * All or nothing: any invalid change saves nothing. Returns the ids of the changed variants.
     */
    public function update(BulkUpdateVariantsRequest $request, BulkUpdateVariantsAction $update, #[CurrentUser] User $actor): JsonResponse
    {
        return response()->json(['data' => ['variant_ids' => $update->handle($actor, $request->toData())]]);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Promotions;

use App\Actions\Promotions\CountPromotionProductsAction;
use App\Actions\Promotions\DeletePromotionAction;
use App\Actions\Promotions\SavePromotionAction;
use App\Actions\Promotions\SearchPromotionsAction;
use App\Data\Promotions\PromotionData;
use App\Enums\PromotionTargetType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Promotions\IndexPromotionsRequest;
use App\Http\Requests\Api\V1\Admin\Promotions\SavePromotionRequest;
use App\Http\Resources\V1\Admin\PromotionResource;
use App\Models\Promotion;
use App\Models\PromotionTarget;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class PromotionController extends Controller
{
    public function index(IndexPromotionsRequest $request, SearchPromotionsAction $search): AnonymousResourceCollection
    {
        return PromotionResource::collection($search->handle($request->toFilters()));
    }

    /**
     * With how many products it applies to.
     */
    public function show(Promotion $promotion, CountPromotionProductsAction $count): PromotionResource
    {
        $promotion->load('targets');

        return (new PromotionResource($promotion))->withProductsCount($count->handle($this->dataOf($promotion)));
    }

    public function store(SavePromotionRequest $request, SavePromotionAction $save, #[CurrentUser] User $actor): PromotionResource
    {
        return new PromotionResource($save->handle($actor, null, $request->toData())->load('targets'));
    }

    public function update(SavePromotionRequest $request, Promotion $promotion, SavePromotionAction $save, #[CurrentUser] User $actor): PromotionResource
    {
        return new PromotionResource($save->handle($actor, $promotion, $request->toData())->load('targets'));
    }

    public function destroy(Promotion $promotion, DeletePromotionAction $delete, #[CurrentUser] User $actor): Response
    {
        $delete->handle($actor, $promotion);

        return response()->noContent();
    }

    /**
     * The form as it is (possibly unsaved): how many products it would apply to.
     */
    public function preview(SavePromotionRequest $request, CountPromotionProductsAction $count): JsonResponse
    {
        return response()->json(['data' => ['products_count' => $count->handle($request->toData())]]);
    }

    private function dataOf(Promotion $promotion): PromotionData
    {
        $ids = fn (PromotionTargetType $type, bool $excluded = false): array => $promotion->targets
            ->filter(fn (PromotionTarget $target): bool => $target->target_type === $type && $target->is_excluded === $excluded)
            ->pluck('target_id')
            ->values()
            ->all();

        return new PromotionData(
            name: $promotion->name,
            type: $promotion->type,
            value: $promotion->value,
            maxDiscount: $promotion->max_discount,
            scope: $promotion->scope,
            productIds: $ids(PromotionTargetType::Product),
            categoryIds: $ids(PromotionTargetType::Category),
            brandIds: $ids(PromotionTargetType::Brand),
            tagIds: $ids(PromotionTargetType::Tag),
            excludedProductIds: $ids(PromotionTargetType::Product, true),
            startsAt: $promotion->starts_at === null ? null : CarbonImmutable::instance($promotion->starts_at),
            endsAt: $promotion->ends_at === null ? null : CarbonImmutable::instance($promotion->ends_at),
            isActive: $promotion->is_active,
        );
    }
}

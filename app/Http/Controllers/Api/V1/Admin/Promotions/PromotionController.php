<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Promotions;

use App\Actions\Promotions\FindPromotionVariantsAction;
use App\Actions\Promotions\ListPromotionsAction;
use App\Actions\Promotions\SavePromotionsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Products\IndexProductsRequest;
use App\Http\Requests\Api\V1\Admin\Promotions\SavePromotionsRequest;
use App\Http\Resources\V1\Admin\PromotionPickerProductResource;
use App\Http\Resources\V1\Admin\PromotionResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PromotionController extends Controller
{
    /**
     * Every promotion row (the page filters and pages them).
     */
    public function index(ListPromotionsAction $list): AnonymousResourceCollection
    {
        return PromotionResource::collection($list->handle());
    }

    /**
     * Saves new, changed and removed rows at once, then returns every row.
     */
    public function save(SavePromotionsRequest $request, SavePromotionsAction $save, ListPromotionsAction $list, #[CurrentUser] User $actor): AnonymousResourceCollection
    {
        $save->handle($actor, $request->toData());

        return PromotionResource::collection($list->handle());
    }

    /**
     * Products with their enabled variants for "Add product" (the product list's filters).
     */
    public function variants(IndexProductsRequest $request, FindPromotionVariantsAction $find): AnonymousResourceCollection
    {
        return PromotionPickerProductResource::collection($find->handle($request->toFilters()));
    }
}

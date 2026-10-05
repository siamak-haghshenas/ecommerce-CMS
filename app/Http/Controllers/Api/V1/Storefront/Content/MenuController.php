<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Storefront\Content;

use App\Enums\MenuLocation;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Storefront\MenuItemResource;
use App\Models\MenuItem;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MenuController extends Controller
{
    /**
     * A menu's visible items in order, with their visible sub-items. Unknown locations are 404.
     */
    public function show(MenuLocation $location): AnonymousResourceCollection
    {
        return MenuItemResource::collection(MenuItemResource::visible(MenuItem::query()->tree($location)->get()));
    }
}

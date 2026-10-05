<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Menus;

use App\Actions\Menus\SaveMenuAction;
use App\Enums\MenuLocation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Menus\SaveMenuRequest;
use App\Http\Resources\V1\Admin\MenuItemResource;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MenuController extends Controller
{
    /**
     * The whole menu, hidden items included (marked with the reason).
     */
    public function show(MenuLocation $location): AnonymousResourceCollection
    {
        return MenuItemResource::collection(MenuItem::query()->tree($location)->get());
    }

    /**
     * Save the whole menu at once.
     */
    public function update(SaveMenuRequest $request, MenuLocation $location, SaveMenuAction $save, #[CurrentUser] User $actor): AnonymousResourceCollection
    {
        $save->handle($actor, $location, $request->toData());

        return $this->show($location);
    }
}

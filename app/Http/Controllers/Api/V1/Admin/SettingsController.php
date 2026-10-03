<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UpdateSettingsRequest;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

class SettingsController extends Controller
{
    /**
     * Every staff member reads the settings (e.g. to show prices in Toman or Rial).
     */
    public function show(Settings $settings): JsonResponse
    {
        return response()->json(['data' => $settings->all()]);
    }

    /**
     * Super admin only.
     */
    public function update(UpdateSettingsRequest $request, Settings $settings, #[CurrentUser] User $actor): JsonResponse
    {
        $values = $request->validated();
        $settings->set($values);

        if ($values !== []) {
            activity('admin')->causedBy($actor)->event('settings.updated')
                ->withProperties(['fields' => array_keys($values)])->log('settings.updated');
        }

        return $this->show($settings);
    }
}

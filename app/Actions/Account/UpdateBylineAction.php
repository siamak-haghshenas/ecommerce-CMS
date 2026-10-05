<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Data\Account\BylineData;
use App\Enums\MediaUsageRole;
use App\Models\User;

final class UpdateBylineAction
{
    /**
     * Set or remove the staff member's public byline. Posts show it as their author; without one
     * they show no author.
     */
    public function handle(User $user, BylineData $data): User
    {
        $user->update([
            'author_name' => $data->name,
            'author_slug' => $data->slug,
            'author_bio' => $data->bio,
        ]);

        $user->syncMediaAssets(MediaUsageRole::Avatar, $data->avatarId === null ? [] : [$data->avatarId]);

        return $user;
    }
}

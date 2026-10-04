<?php

declare(strict_types=1);

namespace App\Actions\Pages;

use App\Models\Page;
use App\Models\User;

final class DeletePageAction
{
    /**
     * Move a page to the trash (restorable for Page::TRASH_DAYS days; its slug stays taken).
     */
    public function handle(User $actor, Page $page): void
    {
        $page->delete();

        activity('admin')
            ->causedBy($actor)
            ->performedOn($page)
            ->event('page.deleted')
            ->withProperties(['title' => $page->title])
            ->log('page.deleted');
    }

    public function restore(User $actor, Page $page): void
    {
        $page->restore();

        activity('admin')
            ->causedBy($actor)
            ->performedOn($page)
            ->event('page.restored')
            ->withProperties(['title' => $page->title])
            ->log('page.restored');
    }
}

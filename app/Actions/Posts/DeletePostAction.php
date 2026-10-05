<?php

declare(strict_types=1);

namespace App\Actions\Posts;

use App\Models\Post;
use App\Models\User;

final class DeletePostAction
{
    /**
     * Move a post to the trash (restorable for Post::TRASH_DAYS days; its slug stays taken).
     */
    public function handle(User $actor, Post $post): void
    {
        $post->delete();

        activity('admin')
            ->causedBy($actor)
            ->performedOn($post)
            ->event('post.deleted')
            ->withProperties(['title' => $post->title])
            ->log('post.deleted');
    }

    public function restore(User $actor, Post $post): void
    {
        $post->restore();

        activity('admin')
            ->causedBy($actor)
            ->performedOn($post)
            ->event('post.restored')
            ->withProperties(['title' => $post->title])
            ->log('post.restored');
    }
}

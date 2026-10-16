<?php

declare(strict_types=1);

namespace App\Actions\Comments;

use App\Enums\CommentableType;
use App\Enums\CommentStatus;
use App\Models\Comment;

final class CountCommentStatesAction
{
    /**
     * Comments per status (and `all`) for the list's tabs, the search and item filters applied.
     *
     * @return array<string, int>
     */
    public function handle(?string $search = null, ?CommentableType $type = null, ?int $itemId = null): array
    {
        $counts = Comment::query()
            ->matching($search, $type, $itemId)
            ->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $result = ['all' => (int) $counts->sum()];

        foreach (CommentStatus::cases() as $status) {
            $result[$status->value] = (int) ($counts[$status->value] ?? 0);
        }

        return $result;
    }
}

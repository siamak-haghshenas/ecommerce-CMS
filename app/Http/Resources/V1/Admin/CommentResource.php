<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Admin;

use App\Models\Comment;
use App\Models\Post;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A comment for moderation: the post or product it is on, its status, who moderated and replied,
 * and the staff-only rejection reason. The customer is only an id (the customer page, for staff
 * allowed to see it, has the rest).
 *
 * @mixin Comment
 */
class CommentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $item = $this->commentable;
        $staff = fn (?User $user): ?array => $user === null ? null : ['id' => $user->id, 'name' => $user->name];

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'display_name' => $this->display_name,
            'body' => $this->body,
            'customer_id' => $this->user_id,
            'item' => $item instanceof Post || $item instanceof Product ? [
                'type' => $this->commentableType()?->value,
                'id' => $item->id,
                'title' => $item->commentableTitle(),
                'slug' => $item->slug,
                'is_deleted' => $item->trashed(),
            ] : null,
            'moderated_by' => $staff($this->moderator),
            'moderated_at' => $this->moderated_at?->toIso8601String(),
            'rejection_reason' => $this->rejection_reason,
            'reply' => $this->reply,
            'replied_by' => $staff($this->replier),
            'replied_at' => $this->replied_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * The relations the resource shows.
     *
     * @return list<string>
     */
    public static function relations(): array
    {
        return ['commentable', 'moderator:id,name', 'replier:id,name'];
    }
}

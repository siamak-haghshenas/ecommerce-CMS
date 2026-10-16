<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Storefront;

use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An approved comment as storefronts show it: the name the customer chose and the text (plain:
 * escape it). The store's reply has no staff name. Never the account, phone, email or status.
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
        return [
            'id' => $this->id,
            'display_name' => $this->display_name,
            'body' => $this->body,
            'created_at' => $this->created_at?->toIso8601String(),
            'reply' => $this->reply === null ? null : [
                'body' => $this->reply,
                'created_at' => $this->replied_at?->toIso8601String(),
            ],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Comments;

use App\Enums\CommentableType;
use App\Models\Post;
use App\Models\Product;
use App\Support\Catalog\StorefrontProductQuery;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class FindCommentableAction
{
    /**
     * The post or product a storefront shows at this slug (published, not in the trash; a product
     * also needs an enabled variant and a visible category).
     *
     * @throws ModelNotFoundException<Post|Product>
     */
    public function handle(CommentableType $type, string $slug): Post|Product
    {
        return match ($type) {
            CommentableType::Post => Post::query()->visible()->where('slug', $slug)->firstOrFail(),
            CommentableType::Product => StorefrontProductQuery::visible()->where('slug', $slug)->firstOrFail(),
        };
    }
}

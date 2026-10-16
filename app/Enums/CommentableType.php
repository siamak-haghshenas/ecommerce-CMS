<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\Post;
use App\Models\Product;
use Illuminate\Database\Eloquent\Model;

/**
 * What customers can comment on. The API names them by value; `comments.commentable_type`
 * stores the model class, like the project's other morph links.
 */
enum CommentableType: string
{
    case Post = 'post';
    case Product = 'product';

    /**
     * @return class-string<Model>
     */
    public function modelClass(): string
    {
        return match ($this) {
            self::Post => Post::class,
            self::Product => Product::class,
        };
    }

    public static function fromClass(string $class): ?self
    {
        foreach (self::cases() as $type) {
            if ($type->modelClass() === $class) {
                return $type;
            }
        }

        return null;
    }
}

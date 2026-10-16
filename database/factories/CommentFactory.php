<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CommentStatus;
use App\Enums\UserRole;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory
{
    /**
     * A pending comment by a customer on a published post (`for($product, 'commentable')` for a product).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'commentable_type' => Post::class,
            'commentable_id' => Post::factory(),
            'user_id' => User::factory()->withRole(UserRole::Customer),
            'display_name' => fake()->firstName(),
            'body' => fake()->sentence(12),
            'status' => CommentStatus::Pending,
        ];
    }

    public function status(CommentStatus $status): static
    {
        return $this->state(['status' => $status, 'moderated_at' => $status === CommentStatus::Pending ? null : now()]);
    }
}

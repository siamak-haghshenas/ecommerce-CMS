<?php

declare(strict_types=1);

use App\Enums\CommentStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Testing\TestResponse;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

function commenterToken(?User $customer = null): string
{
    return ($customer ?? User::factory()->withRole(UserRole::Customer)->create())
        ->createToken('storefront', ['customer'])->plainTextToken;
}

/**
 * @param  array<string, mixed>  $data
 */
function postComment(Post $post, array $data = [], ?string $token = null): TestResponse
{
    return asStorefront($token ?? commenterToken(), 'POST', route('api.v1.blog.posts.comments.store', $post->slug), [
        'display_name' => 'Sara',
        'body' => 'A very useful post.',
        ...$data,
    ]);
}

describe('posting', function () {
    it('needs a logged-in customer', function () {
        postJson(route('api.v1.blog.posts.comments.store', Post::factory()->create()->slug), ['display_name' => 'Sara', 'body' => 'Hi'])
            ->assertUnauthorized();
    });

    it('always starts pending, whatever status the request sends', function () {
        $post = Post::factory()->create();
        $customer = User::factory()->withRole(UserRole::Customer)->create();

        postComment($post, ['status' => 'approved', 'moderated_by' => $customer->id, 'body' => "  Line one\r\n\r\n\r\n\r\nLine two  "], commenterToken($customer))
            ->assertCreated()
            ->assertJsonPath('data.display_name', 'Sara')
            ->assertJsonPath('data.body', "Line one\n\nLine two")
            ->assertJsonPath('meta.status', 'pending')
            ->assertJsonMissingPath('data.status');

        $comment = Comment::query()->sole();

        expect($comment->status)->toBe(CommentStatus::Pending)
            ->and($comment->user_id)->toBe($customer->id)
            ->and($comment->moderated_by)->toBeNull();
    });

    it('refuses posts that are not public or are closed to comments', function () {
        postComment(Post::factory()->draft()->create())->assertNotFound();
        postComment(Post::factory()->scheduled()->create())->assertNotFound();
        $trashed = Post::factory()->create();
        $trashed->delete();
        postComment($trashed)->assertNotFound();

        postComment(Post::factory()->create(['comments_enabled' => false]))
            ->assertConflict()
            ->assertJsonPath('code', 'comments_closed');

        expect(Comment::query()->count())->toBe(0);
    });

    it('checks the name and the text', function () {
        $post = Post::factory()->create();

        postComment($post, ['display_name' => '', 'body' => str_repeat('a', Comment::BODY_MAX + 1)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['display_name', 'body']);
    });

    it('limits how fast one customer posts', function () {
        $post = Post::factory()->create();
        $token = commenterToken();

        foreach (range(1, 3) as $attempt) {
            postComment($post, token: $token)->assertCreated();
        }

        postComment($post, token: $token)->assertTooManyRequests();
    });
});

describe('public list', function () {
    it('shows only approved comments, oldest first, with the store reply and no account details', function () {
        $post = Post::factory()->create();
        $first = Comment::factory()->for($post, 'commentable')->status(CommentStatus::Approved)->create(['created_at' => now()->subDay()]);
        $second = Comment::factory()->for($post, 'commentable')->status(CommentStatus::Approved)->create();
        $second->forceFill(['reply' => 'Thank you!', 'replied_at' => now()])->save();
        foreach ([CommentStatus::Pending, CommentStatus::Rejected, CommentStatus::Spam] as $hidden) {
            Comment::factory()->for($post, 'commentable')->status($hidden)->create();
        }

        getJson(route('api.v1.blog.posts.comments.index', $post->slug))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $first->id)
            ->assertJsonPath('data.1.reply.body', 'Thank you!')
            ->assertJsonMissingPath('data.0.status')
            ->assertJsonMissingPath('data.0.user_id')
            ->assertJsonMissingPath('data.0.customer_id')
            ->assertJsonMissingPath('data.1.reply.replied_by');

        getJson(route('api.v1.blog.posts.show', $post->slug))->assertOk()->assertJsonPath('data.comments_count', 2);
    });

    it('is not found for posts that are not public', function () {
        $draft = Post::factory()->draft()->create();
        Comment::factory()->for($draft, 'commentable')->status(CommentStatus::Approved)->create();

        getJson(route('api.v1.blog.posts.comments.index', $draft->slug))->assertNotFound();
    });
});

describe('products', function () {
    /**
     * A product the storefront shows: published, with an enabled variant, in a visible category.
     *
     * @param  array<string, mixed>  $attributes
     */
    function commentableProduct(array $attributes = []): Product
    {
        $product = Product::factory()->published()->inCategory(Category::factory()->create())->create($attributes);
        ProductVariant::factory()->for($product)->create();

        return $product;
    }

    it('takes pending comments on visible products and lists the approved ones', function () {
        $product = commentableProduct();
        $token = commenterToken();

        asStorefront($token, 'POST', route('api.v1.products.comments.store', $product->slug), ['display_name' => 'Ali', 'body' => 'Does it run small?', 'status' => 'approved'])
            ->assertCreated()
            ->assertJsonPath('meta.status', 'pending');

        $comment = Comment::query()->sole();
        expect($comment->commentable_type)->toBe(Product::class)
            ->and($comment->commentable_id)->toBe($product->id)
            ->and($comment->status)->toBe(CommentStatus::Pending);

        getJson(route('api.v1.products.comments.index', $product->slug))->assertOk()->assertJsonCount(0, 'data');

        $comment->forceFill(['status' => CommentStatus::Approved])->save();
        // A post comment with the same id as the product never leaks into the product's list.
        Comment::factory()->status(CommentStatus::Approved)->create(['commentable_id' => $product->id]);

        getJson(route('api.v1.products.comments.index', $product->slug))
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.body', 'Does it run small?');
        getJson(route('api.v1.products.show', $product->slug))
            ->assertJsonPath('data.comments_enabled', true)
            ->assertJsonPath('data.comments_count', 1);
    });

    it('refuses products the storefront does not show or that are closed to comments', function () {
        $draft = Product::factory()->create();
        ProductVariant::factory()->for($draft)->create();

        asStorefront(commenterToken(), 'POST', route('api.v1.products.comments.store', $draft->slug), ['display_name' => 'Ali', 'body' => 'Hi'])
            ->assertNotFound();
        asStorefront(commenterToken(), 'POST', route('api.v1.products.comments.store', commentableProduct(['comments_enabled' => false])->slug), ['display_name' => 'Ali', 'body' => 'Hi'])
            ->assertConflict()
            ->assertJsonPath('code', 'comments_closed');

        expect(Comment::query()->count())->toBe(0);
    });

    it('keeps comments of a product in the trash and removes them with it for good', function () {
        $product = commentableProduct();
        Comment::factory()->for($product, 'commentable')->create();

        $product->delete();
        expect(Comment::query()->count())->toBe(1);

        $product->forceDelete();
        expect(Comment::query()->count())->toBe(0);
    });
});

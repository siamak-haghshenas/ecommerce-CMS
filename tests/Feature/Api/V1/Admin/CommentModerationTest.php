<?php

declare(strict_types=1);

use App\Enums\CommentStatus;
use App\Enums\UserRole;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Product;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

function moderator(UserRole $role = UserRole::Author): User
{
    return User::factory()->withRole($role)->create();
}

describe('access', function () {
    it('lets super admins, admins and authors moderate, and nobody else', function () {
        $comment = Comment::factory()->create();

        getJson(route('api.v1.admin.comments.index'))->assertUnauthorized();

        foreach ([UserRole::SuperAdmin, UserRole::Admin, UserRole::Author] as $role) {
            actingAs(moderator($role))->getJson(route('api.v1.admin.comments.index'))->assertOk();
        }

        // A customer's storefront token never reaches the admin API.
        $token = $comment->customer->createToken('storefront', ['customer'])->plainTextToken;
        asStorefront($token, 'PUT', route('api.v1.admin.comments.status', $comment), ['status' => 'approved'])->assertForbidden();

        expect($comment->refresh()->status)->toBe(CommentStatus::Pending);
    });
});

describe('list', function () {
    it('shows pending comments by default, filters by status, kind, item and text, and counts each status', function () {
        $post = Post::factory()->create(['title' => 'Winter tips']);
        $product = Product::factory()->create(['name' => 'Wool scarf']);
        $pending = Comment::factory()->for($post, 'commentable')->create(['body' => 'Lovely winter ideas']);
        Comment::factory()->for($post, 'commentable')->status(CommentStatus::Approved)->create();
        $onProduct = Comment::factory()->for($product, 'commentable')->status(CommentStatus::Approved)->create();
        Comment::factory()->status(CommentStatus::Spam)->create(['body' => 'Buy cheap watches']);

        actingAs(moderator())
            ->getJson(route('api.v1.admin.comments.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $pending->id)
            ->assertJsonPath('data.0.item', ['type' => 'post', 'id' => $post->id, 'title' => 'Winter tips', 'slug' => $post->slug, 'is_deleted' => false])
            ->assertJsonPath('data.0.customer_id', $pending->user_id)
            ->assertJsonPath('meta.status_counts', ['all' => 4, 'pending' => 1, 'approved' => 2, 'rejected' => 0, 'spam' => 1]);

        actingAs(moderator())
            ->getJson(route('api.v1.admin.comments.index', ['status' => 'all', 'type' => 'post', 'item_id' => $post->id]))
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.status_counts.all', 2);

        actingAs(moderator())
            ->getJson(route('api.v1.admin.comments.index', ['status' => 'all', 'type' => 'product']))
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $onProduct->id)
            ->assertJsonPath('data.0.item.type', 'product')
            ->assertJsonPath('data.0.item.title', 'Wool scarf');

        actingAs(moderator())
            ->getJson(route('api.v1.admin.comments.index', ['status' => 'spam', 'q' => 'watches']))
            ->assertJsonCount(1, 'data');

        Comment::factory()->for($product, 'commentable')->count(2)->create();

        actingAs(moderator())->getJson(route('api.v1.admin.comments.pending-count'))->assertJsonPath('data.pending', ['post' => 1, 'product' => 2]);
    });
});

describe('moderation', function () {
    it('approves a comment, recording who and when, and makes it public', function () {
        $comment = Comment::factory()->create();
        $staff = moderator();

        actingAs($staff)
            ->putJson(route('api.v1.admin.comments.status', $comment), ['status' => 'approved'])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.moderated_by.id', $staff->id);

        expect($comment->refresh()->moderated_at)->not->toBeNull();

        getJson(route('api.v1.blog.posts.comments.index', $comment->commentable->slug))->assertJsonCount(1, 'data');

        $entry = Activity::query()->where('event', 'comment.approved')->sole();
        expect($entry->properties->all())->toBe(['type' => 'post', 'item_id' => $comment->commentable_id, 'from' => 'pending', 'to' => 'approved']);
    });

    it('keeps a reason for staff only while rejected', function () {
        $comment = Comment::factory()->create();
        $staff = moderator();

        actingAs($staff)
            ->putJson(route('api.v1.admin.comments.status', $comment), ['status' => 'rejected', 'reason' => 'Off topic'])
            ->assertOk()
            ->assertJsonPath('data.rejection_reason', 'Off topic');

        expect(Activity::query()->where('event', 'comment.rejected')->sole()->properties->has('reason'))->toBeFalse();

        actingAs($staff)
            ->putJson(route('api.v1.admin.comments.status', $comment), ['status' => 'spam', 'reason' => 'Ads'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');

        actingAs($staff)
            ->putJson(route('api.v1.admin.comments.status', $comment), ['status' => 'spam'])
            ->assertOk()
            ->assertJsonPath('data.status', 'spam')
            ->assertJsonPath('data.rejection_reason', null);
    });

    it('refuses going back to pending, unknown statuses and the same status again', function () {
        $comment = Comment::factory()->status(CommentStatus::Approved)->create();
        $staff = moderator();

        foreach (['pending', 'published', ''] as $status) {
            actingAs($staff)
                ->putJson(route('api.v1.admin.comments.status', $comment), ['status' => $status])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('status');
        }

        actingAs($staff)
            ->putJson(route('api.v1.admin.comments.status', $comment), ['status' => 'approved'])
            ->assertConflict()
            ->assertJsonPath('code', 'comment_status_unchanged');
    });

    it('never lets staff moderate their own comment', function () {
        $staff = moderator();
        $comment = Comment::factory()->create(['user_id' => $staff->id]);

        actingAs($staff)
            ->putJson(route('api.v1.admin.comments.status', $comment), ['status' => 'approved'])
            ->assertForbidden()
            ->assertJsonPath('code', 'comment_own');

        actingAs($staff)
            ->postJson(route('api.v1.admin.comments.status.bulk'), ['ids' => [Comment::factory()->create()->id, $comment->id], 'status' => 'approved'])
            ->assertForbidden();

        expect(Comment::query()->where('status', CommentStatus::Approved)->count())->toBe(0);
    });

    it('moderates several comments at once, leaving those that already have the status', function () {
        $pending = Comment::factory()->count(2)->create();
        $spam = Comment::factory()->status(CommentStatus::Spam)->create();

        actingAs(moderator())
            ->postJson(route('api.v1.admin.comments.status.bulk'), ['ids' => [...$pending->modelKeys(), $spam->id], 'status' => 'spam'])
            ->assertOk()
            ->assertJsonPath('data.changed', 2);

        expect(Comment::query()->where('status', CommentStatus::Spam)->count())->toBe(3)
            ->and(Activity::query()->where('event', 'comment.spammed')->count())->toBe(2);

        actingAs(moderator())
            ->postJson(route('api.v1.admin.comments.status.bulk'), ['ids' => [$spam->id, 999_999], 'status' => 'approved'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ids.1');

        expect($spam->refresh()->status)->toBe(CommentStatus::Spam);
    });
});

describe('reply and delete', function () {
    it('writes and removes the store reply', function () {
        $comment = Comment::factory()->status(CommentStatus::Approved)->create();
        $staff = moderator();

        actingAs($staff)
            ->putJson(route('api.v1.admin.comments.reply.update', $comment), ['reply' => ' Thanks for reading! '])
            ->assertOk()
            ->assertJsonPath('data.reply', 'Thanks for reading!')
            ->assertJsonPath('data.replied_by.id', $staff->id);

        getJson(route('api.v1.blog.posts.comments.index', $comment->commentable->slug))->assertJsonPath('data.0.reply.body', 'Thanks for reading!');

        actingAs($staff)->deleteJson(route('api.v1.admin.comments.reply.destroy', $comment))->assertOk()->assertJsonPath('data.reply', null);

        expect(Activity::query()->whereIn('event', ['comment.replied', 'comment.reply_deleted'])->count())->toBe(2);
    });

    it('deletes one comment or a selection for good', function () {
        $one = Comment::factory()->create();
        $more = Comment::factory()->count(2)->create();
        $kept = Comment::factory()->create();

        actingAs(moderator())->deleteJson(route('api.v1.admin.comments.destroy', $one))->assertNoContent();
        actingAs(moderator())
            ->postJson(route('api.v1.admin.comments.destroy.bulk'), ['ids' => $more->modelKeys()])
            ->assertOk()
            ->assertJsonPath('data.deleted', 2);

        expect(Comment::query()->pluck('id')->all())->toBe([$kept->id])
            ->and(Activity::query()->where('event', 'comment.deleted')->count())->toBe(3);
    });

    it('deletes the comments of an anonymised customer', function () {
        $comment = Comment::factory()->status(CommentStatus::Approved)->create();

        actingAs(moderator(UserRole::SuperAdmin))
            ->postJson(route('api.v1.admin.customers.anonymise', ['customer' => $comment->user_id]))
            ->assertOk();

        expect(Comment::query()->count())->toBe(0);
    });
});

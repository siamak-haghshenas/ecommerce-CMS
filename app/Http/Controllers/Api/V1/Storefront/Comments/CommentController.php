<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Storefront\Comments;

use App\Actions\Comments\AddCommentAction;
use App\Actions\Comments\FindCommentableAction;
use App\Enums\CommentableType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Storefront\Comments\CommentsIndexRequest;
use App\Http\Requests\Api\V1\Storefront\Comments\StoreCommentRequest;
use App\Http\Resources\V1\Storefront\CommentResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Comments on blog posts and products; the route says which (`type` route default).
 */
class CommentController extends Controller
{
    /**
     * A visible post's or product's approved comments, oldest first, paginated.
     */
    public function index(CommentsIndexRequest $request, string $slug, CommentableType $type, FindCommentableAction $find): AnonymousResourceCollection
    {
        return CommentResource::collection(
            $find->handle($type, $slug)->comments()->approved()->oldest()->oldest('id')->paginate($request->perPage()),
        );
    }

    /**
     * A logged-in customer's comment; it waits for moderation (`status` pending) before it shows.
     */
    public function store(StoreCommentRequest $request, string $slug, CommentableType $type, FindCommentableAction $find, AddCommentAction $add, #[CurrentUser] User $customer): JsonResponse
    {
        $comment = $add->handle($customer, $find->handle($type, $slug), $request->toData());

        return (new CommentResource($comment))
            ->additional(['meta' => ['status' => $comment->status->value]])
            ->response()
            ->setStatusCode(201);
    }
}

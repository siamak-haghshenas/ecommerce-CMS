<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Posts;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Admin\PostRevisionResource;
use App\Models\Post;
use App\Models\PostRevision;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PostRevisionController extends Controller
{
    /**
     * The post's kept revisions, newest first (without their content).
     */
    public function index(Post $post): AnonymousResourceCollection
    {
        return PostRevisionResource::collection($post->revisions()->with('saver')->get());
    }

    /**
     * One revision with every saved field, to preview it and load it into the editor.
     */
    public function show(Post $post, PostRevision $revision): PostRevisionResource
    {
        return (new PostRevisionResource($revision->load(['saver', 'image.media', 'ogImage.media'])))->detailed();
    }
}

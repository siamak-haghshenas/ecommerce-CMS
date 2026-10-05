<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Products;

use App\Actions\Products\ManageTagsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Products\TagRequest;
use App\Http\Resources\V1\Admin\TagResource;
use App\Models\Tag;
use App\Models\User;
use App\Support\Seo\SeoText;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class TagController extends Controller
{
    /**
     * All tags with their product and post counts (`q` filters, e.g. for suggestions while typing).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $words = SeoText::words($request->string('q')->toString());

        $tags = Tag::query()->withCount(['products', 'posts'])->orderBy('name')->get()
            ->filter(fn (Tag $tag): bool => $words === [] || str_contains(implode(' ', SeoText::words($tag->name)), implode(' ', $words)))
            ->values();

        return TagResource::collection($tags);
    }

    public function update(TagRequest $request, Tag $tag, ManageTagsAction $tags, #[CurrentUser] User $actor): TagResource
    {
        return new TagResource($tags->rename($actor, $tag, $request->string('name')->trim()->toString())->loadCount(['products', 'posts']));
    }

    public function merge(TagRequest $request, Tag $tag, ManageTagsAction $tags, #[CurrentUser] User $actor): TagResource
    {
        $into = Tag::query()->findOrFail($request->integer('into_tag_id'));

        return new TagResource($tags->merge($actor, $tag, $into)->loadCount(['products', 'posts']));
    }

    public function destroy(Tag $tag, ManageTagsAction $tags, #[CurrentUser] User $actor): Response
    {
        $tags->delete($actor, $tag);

        return response()->noContent();
    }
}

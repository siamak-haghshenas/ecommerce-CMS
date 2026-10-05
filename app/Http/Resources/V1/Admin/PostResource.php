<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Admin;

use App\Models\Category;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A post for the admin editor.
 *
 * @mixin Post
 */
class PostResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $autosave = $this->autosaves()->where('user_id', $request->user()?->getAuthIdentifier())->with(['image.media', 'ogImage.media'])->first();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'content' => $this->content,
            'status' => $this->status->value,
            'state' => $this->state(),
            'published_at' => $this->published_at?->toIso8601String(),
            'image' => $this->imageAsset()?->picture(),
            // The account name for staff; storefronts only ever get the public byline.
            'author' => $this->author === null ? null : [
                'id' => $this->author->id,
                'name' => $this->author->name,
                'author_name' => $this->author->author_name,
                'has_byline' => $this->author->hasByline(),
            ],
            'comments_enabled' => $this->comments_enabled,
            'categories' => $this->categories->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'is_primary' => (bool) $category->getRelationValue('pivot')?->getAttribute('is_primary'),
            ])->values()->all(),
            'tags' => $this->tags->pluck('name')->all(),
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'focus_keyphrase' => $this->focus_keyphrase,
            'robots_noindex' => $this->robots_noindex,
            'canonical_url' => $this->canonical_url,
            'og_title' => $this->og_title,
            'og_description' => $this->og_description,
            'og_image' => $this->ogImageAsset()?->picture(),
            'seo_score' => $this->seo_score,
            'readability_score' => $this->readability_score,
            'created_by' => $this->creator === null ? null : ['id' => $this->creator->id, 'name' => $this->creator->name],
            'updated_by' => $this->editor === null ? null : ['id' => $this->editor->id, 'name' => $this->editor->name],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            // The viewer's own unsaved form, only when it holds changes made after the last save.
            'autosave' => $autosave !== null && $autosave->isNewerThan($this->resource) ? new PostAutosaveResource($autosave) : null,
        ];
    }

    /**
     * The relations this resource reads.
     *
     * @return list<string>
     */
    public static function relations(): array
    {
        return ['imageAssets.media', 'ogImageAssets.media', 'creator', 'editor', 'author', 'categories', 'tags'];
    }
}

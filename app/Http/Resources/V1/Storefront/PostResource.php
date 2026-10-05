<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Storefront;

use App\Data\Categories\CategoryNode;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Support\CategoryTree;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A published post. The list (cards) has no content; the post itself adds the content, every
 * visible category, the breadcrumb, tags and the SEO block. The author is the staff member's public
 * byline, or null when they have none: account names are never exposed. Never the focus keyphrase,
 * scores or revisions.
 *
 * @mixin Post
 */
class PostResource extends JsonResource
{
    /** Whether to include the content (the post itself rather than the list). */
    public bool $detailed = false;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $tree = CategoryTree::current();
        $categories = $this->categories->filter(fn (Category $category): bool => $tree->isVisible($category->id))->values();
        $primary = $categories->first(fn (Category $category): bool => (bool) $category->getRelationValue('pivot')?->getAttribute('is_primary')) ?? $categories->first();
        $image = $this->imageAsset()?->picture();

        $data = [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'image' => $image,
            'author' => $this->byline(),
            'primary_category' => $primary === null ? null : ['slug' => $primary->slug, 'name' => $primary->name],
            'robots_noindex' => $this->robots_noindex,
            'published_at' => $this->published_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        if (! $this->detailed) {
            return $data;
        }

        $ogImage = $this->ogImageAsset()?->picture();

        return [
            ...$data,
            'content' => $this->content,
            'categories' => $categories->map(fn (Category $category): array => [
                'slug' => $category->slug,
                'name' => $category->name,
                'is_primary' => $category->is($primary),
            ])->all(),
            'breadcrumb' => $primary === null ? [] : [
                ...array_map(fn (CategoryNode $node): array => ['slug' => $node->slug, 'name' => $node->name], $tree->ancestors($primary->id)),
                ['slug' => $primary->slug, 'name' => $primary->name],
            ],
            'tags' => $this->tags->map(fn (Tag $tag): array => ['slug' => $tag->slug, 'name' => $tag->name])->all(),
            'comments_enabled' => $this->comments_enabled,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'canonical_url' => $this->canonical_url,
            'og_title' => $this->og_title,
            'og_description' => $this->og_description,
            'og_image' => $ogImage,
            // Ready to use, with the fallbacks applied (the fields above are as the admin typed them).
            'seo' => [
                'title' => $this->meta_title ?? $this->title,
                'description' => $this->meta_description ?? $this->excerpt,
                'canonical_url' => $this->canonical_url,
                'robots_noindex' => $this->robots_noindex,
                'og' => [
                    'title' => $this->og_title ?? $this->meta_title ?? $this->title,
                    'description' => $this->og_description ?? $this->meta_description ?? $this->excerpt,
                    'image' => $ogImage ?? $image,
                ],
            ],
        ];
    }

    public function detailed(): self
    {
        $this->detailed = true;

        return $this;
    }

    /**
     * The relations this resource reads.
     *
     * @return list<string>
     */
    public static function relations(): array
    {
        return ['imageAssets.media', 'ogImageAssets.media', 'author.avatarAssets.media', 'categories', 'tags'];
    }

    /**
     * The author's public byline, or null without one.
     *
     * @return array{name: string, slug: string, bio: string|null, avatar: array<string, mixed>|null}|null
     */
    private function byline(): ?array
    {
        $author = $this->author;

        if ($author === null || ! $author->hasByline()) {
            return null;
        }

        return [
            'name' => (string) $author->author_name,
            'slug' => (string) $author->author_slug,
            'bio' => $author->author_bio,
            'avatar' => $author->avatarAssets->first()?->picture(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Support;

use App\Data\Categories\CategoryNode;
use App\Enums\CategoryType;
use App\Models\Category;
use Illuminate\Support\Facades\Cache;

/**
 * Every category (product and blog trees), loaded in one query and cached as plain arrays. A tree
 * never mixes types, so only the top level is filtered by type.
 * Category saves and deletes clear the cache (see Category::booted()).
 */
final class CategoryTree
{
    // Versioned: bump it whenever the cached row shape changes.
    private const string CACHE_KEY = 'category-tree.v3';

    /** @var array<int, CategoryNode> */
    private array $nodes = [];

    /** @var array<int, list<int>> Child ids per parent id, in display order; 0 holds the top level. */
    private array $childIds = [];

    /** @var array<string, int> Keyed "type|slug": slugs are unique per type. */
    private array $idsBySlug = [];

    /**
     * @param  list<CategoryNode>  $nodes
     */
    public function __construct(array $nodes)
    {
        usort($nodes, fn (CategoryNode $a, CategoryNode $b): int => [$a->position, $a->id] <=> [$b->position, $b->id]);

        foreach ($nodes as $node) {
            $this->nodes[$node->id] = $node;
            $this->idsBySlug[$node->type->value.'|'.$node->slug] = $node->id;
            $this->childIds[$node->parentId ?? 0][] = $node->id;
        }
    }

    public static function current(): self
    {
        $rows = Cache::rememberForever(self::CACHE_KEY, fn (): array => Category::query()
            ->with('imageAssets.media')
            ->get()
            ->map(fn (Category $category): array => (new CategoryNode(
                id: $category->id,
                type: $category->type,
                parentId: $category->parent_id,
                name: $category->name,
                slug: $category->slug,
                position: $category->position,
                isActive: $category->is_active,
                image: $category->imageAsset()?->picture(),
                focusKeyphrase: $category->focus_keyphrase,
                seoScore: $category->seo_score,
                readabilityScore: $category->readability_score,
            ))->toArray())
            ->all());

        return new self(array_map(CategoryNode::fromArray(...), $rows));
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function find(int $id): ?CategoryNode
    {
        return $this->nodes[$id] ?? null;
    }

    public function findBySlug(string $slug, CategoryType $type): ?CategoryNode
    {
        $id = $this->idsBySlug[$type->value.'|'.$slug] ?? null;

        return $id === null ? null : $this->nodes[$id];
    }

    /**
     * Direct children in display order; for the top level (null) only those of the given type.
     *
     * @return list<CategoryNode>
     */
    public function children(?int $parentId, ?CategoryType $type = null): array
    {
        $children = array_map(fn (int $id): CategoryNode => $this->nodes[$id], $this->childIds[$parentId ?? 0] ?? []);

        return $type === null ? $children : array_values(array_filter($children, fn (CategoryNode $node): bool => $node->type === $type));
    }

    /**
     * Ancestors from the top level down to the direct parent (for breadcrumbs).
     *
     * @return list<CategoryNode>
     */
    public function ancestors(int $id): array
    {
        $ancestors = [];
        $parentId = $this->find($id)?->parentId;

        // The visited check only guards against corrupted data; moves are validated by canMoveUnder().
        while ($parentId !== null && isset($this->nodes[$parentId]) && ! isset($ancestors[$parentId])) {
            $ancestors[$parentId] = $this->nodes[$parentId];
            $parentId = $this->nodes[$parentId]->parentId;
        }

        return array_reverse(array_values($ancestors));
    }

    /**
     * Ids of every category below this one, at any depth.
     *
     * @return list<int>
     */
    public function descendantIds(int $id): array
    {
        $descendants = [];
        $pending = $this->childIds[$id] ?? [];

        while ($pending !== []) {
            $childId = array_shift($pending);

            if (! isset($descendants[$childId])) {
                $descendants[$childId] = $childId;
                array_push($pending, ...($this->childIds[$childId] ?? []));
            }
        }

        return array_values($descendants);
    }

    /**
     * Ids of every category of this type shown on the storefront (active, with only active ancestors).
     *
     * @return list<int>
     */
    public function visibleIds(CategoryType $type): array
    {
        $visible = [];
        $pending = array_filter($this->children(null, $type), fn (CategoryNode $node): bool => $node->isActive);

        while ($pending !== []) {
            $node = array_shift($pending);
            $visible[] = $node->id;
            array_push($pending, ...array_filter($this->children($node->id), fn (CategoryNode $child): bool => $child->isActive));
        }

        return $visible;
    }

    /**
     * Shown on the storefront: the category and all its ancestors are active.
     */
    public function isVisible(int $id): bool
    {
        $node = $this->find($id);

        if ($node === null || ! $node->isActive) {
            return false;
        }

        foreach ($this->ancestors($id) as $ancestor) {
            if (! $ancestor->isActive) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether the category may be placed under the given parent (null = top level): never under
     * itself, one of its own descendants or a category of the other type.
     */
    public function canMoveUnder(int $id, ?int $parentId): bool
    {
        if ($parentId === null) {
            return true;
        }

        return $parentId !== $id
            && $this->find($parentId) !== null
            && $this->find($parentId)->type === $this->find($id)?->type
            && ! in_array($parentId, $this->descendantIds($id), true);
    }
}

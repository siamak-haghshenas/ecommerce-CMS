<?php

declare(strict_types=1);

namespace App\Support;

use App\Data\Categories\CategoryNode;
use App\Models\Category;
use Illuminate\Support\Facades\Cache;

/**
 * The whole category tree, loaded in one query and cached as plain arrays.
 * Category saves and deletes clear the cache (see Category::booted()).
 */
final class CategoryTree
{
    // Versioned: bump it whenever the cached row shape changes.
    private const string CACHE_KEY = 'category-tree.v2';

    /** @var array<int, CategoryNode> */
    private array $nodes = [];

    /** @var array<int, list<int>> Child ids per parent id, in display order; 0 holds the top level. */
    private array $childIds = [];

    /** @var array<string, int> */
    private array $idsBySlug = [];

    /**
     * @param  list<CategoryNode>  $nodes
     */
    public function __construct(array $nodes)
    {
        usort($nodes, fn (CategoryNode $a, CategoryNode $b): int => [$a->position, $a->id] <=> [$b->position, $b->id]);

        foreach ($nodes as $node) {
            $this->nodes[$node->id] = $node;
            $this->idsBySlug[$node->slug] = $node->id;
            $this->childIds[$node->parentId ?? 0][] = $node->id;
        }
    }

    public static function current(): self
    {
        $rows = Cache::rememberForever(self::CACHE_KEY, fn (): array => Category::query()
            ->with('media')
            ->get()
            ->map(fn (Category $category): array => (new CategoryNode(
                id: $category->id,
                parentId: $category->parent_id,
                name: $category->name,
                slug: $category->slug,
                position: $category->position,
                isActive: $category->is_active,
                image: $category->imageUrls(),
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

    public function findBySlug(string $slug): ?CategoryNode
    {
        return isset($this->idsBySlug[$slug]) ? $this->nodes[$this->idsBySlug[$slug]] : null;
    }

    /**
     * Direct children in display order; null for the top level.
     *
     * @return list<CategoryNode>
     */
    public function children(?int $parentId): array
    {
        return array_map(fn (int $id): CategoryNode => $this->nodes[$id], $this->childIds[$parentId ?? 0] ?? []);
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
     * Whether the category may be placed under the given parent (null = top level):
     * never under itself or one of its own descendants.
     */
    public function canMoveUnder(int $id, ?int $parentId): bool
    {
        if ($parentId === null) {
            return true;
        }

        return $parentId !== $id
            && $this->find($parentId) !== null
            && ! in_array($parentId, $this->descendantIds($id), true);
    }
}

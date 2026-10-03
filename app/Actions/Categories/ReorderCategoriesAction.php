<?php

declare(strict_types=1);

namespace App\Actions\Categories;

use App\Data\Categories\CategoryNode;
use App\Exceptions\CategoryException;
use App\Models\Category;
use App\Models\User;
use App\Support\CategoryTree;
use Illuminate\Support\Facades\DB;

final class ReorderCategoriesAction
{
    /**
     * Set the complete child order of one parent (null = top level). The ids may include
     * categories moved in from elsewhere, but must include every current child.
     *
     * @param  list<int>  $ids
     *
     * @throws CategoryException
     */
    public function handle(User $actor, ?int $parentId, array $ids): void
    {
        $tree = CategoryTree::current();

        $currentChildIds = array_map(fn (CategoryNode $node): int => $node->id, $tree->children($parentId));

        if (array_diff($currentChildIds, $ids) !== []) {
            throw CategoryException::treeChanged();
        }

        foreach ($ids as $id) {
            if (! $tree->canMoveUnder($id, $parentId)) {
                throw CategoryException::invalidParent();
            }
        }

        DB::transaction(function () use ($actor, $parentId, $ids): void {
            $categories = Category::query()->whereKey($ids)->get()->keyBy('id');
            $orderChanged = false;

            foreach ($ids as $position => $id) {
                /** @var Category $category */
                $category = $categories->get($id);
                $previousParentId = $category->parent_id;

                $category->fill(['parent_id' => $parentId, 'position' => $position]);

                if (! $category->isDirty()) {
                    continue;
                }

                $orderChanged = true;
                $category->save();

                if ($previousParentId !== $parentId) {
                    activity('admin')
                        ->causedBy($actor)
                        ->performedOn($category)
                        ->event('category.moved')
                        ->withProperties(['from' => $previousParentId, 'to' => $parentId])
                        ->log('category.moved');
                }
            }

            if ($orderChanged) {
                $entry = activity('admin')
                    ->causedBy($actor)
                    ->event('category.reordered')
                    ->withProperties(['parent_id' => $parentId]);

                $parent = $parentId === null ? null : Category::query()->find($parentId);

                if ($parent !== null) {
                    $entry->performedOn($parent);
                }

                $entry->log('category.reordered');
            }
        });
    }
}

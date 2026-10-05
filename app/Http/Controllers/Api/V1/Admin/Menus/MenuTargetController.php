<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Menus;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Support\CategoryTree;
use Illuminate\Http\JsonResponse;

class MenuTargetController extends Controller
{
    /**
     * What menu items can link to: every page (not in the trash) and the category tree in order,
     * flattened with depth. Staff who manage menus need this without the pages or categories
     * permissions.
     */
    public function __invoke(): JsonResponse
    {
        $tree = CategoryTree::current();
        $categories = [];
        $walk = function (?int $parentId, int $depth) use (&$walk, &$categories, $tree): void {
            foreach ($tree->children($parentId) as $node) {
                $categories[] = ['id' => $node->id, 'name' => $node->name, 'slug' => $node->slug, 'depth' => $depth, 'is_visible' => $tree->isVisible($node->id)];
                $walk($node->id, $depth + 1);
            }
        };
        $walk(null, 0);

        return response()->json(['data' => [
            'pages' => Page::query()->orderBy('title')->get(['id', 'title', 'slug', 'status', 'published_at'])
                ->map(fn (Page $page): array => ['id' => $page->id, 'title' => $page->title, 'slug' => $page->slug, 'state' => $page->state()])
                ->all(),
            'categories' => $categories,
        ]]);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Menus;

use App\Enums\CategoryType;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Support\CategoryTree;
use Illuminate\Http\JsonResponse;

class MenuTargetController extends Controller
{
    /**
     * What menu items can link to: every page (not in the trash) and the product and blog category
     * trees in order, flattened with depth. Staff who manage menus need this without the pages or categories
     * permissions.
     */
    public function __invoke(): JsonResponse
    {
        $tree = CategoryTree::current();
        $categories = [];
        $walk = function (?int $parentId, CategoryType $type, int $depth) use (&$walk, &$categories, $tree): void {
            foreach ($tree->children($parentId, $type) as $node) {
                $categories[] = [
                    'id' => $node->id,
                    'type' => $node->type->value,
                    'name' => $node->name,
                    'slug' => $node->slug,
                    'depth' => $depth,
                    'is_visible' => $tree->isVisible($node->id),
                ];
                $walk($node->id, $type, $depth + 1);
            }
        };

        foreach (CategoryType::cases() as $type) {
            $walk(null, $type, 0);
        }

        return response()->json(['data' => [
            'pages' => Page::query()->orderBy('title')->get(['id', 'title', 'slug', 'status', 'published_at'])
                ->map(fn (Page $page): array => ['id' => $page->id, 'title' => $page->title, 'slug' => $page->slug, 'state' => $page->state()])
                ->all(),
            'categories' => $categories,
        ]]);
    }
}

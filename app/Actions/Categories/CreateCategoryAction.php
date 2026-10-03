<?php

declare(strict_types=1);

namespace App\Actions\Categories;

use App\Actions\Seo\ScoreSeoAction;
use App\Data\Categories\CategoryData;
use App\Models\Category;
use App\Models\User;

final class CreateCategoryAction
{
    public function __construct(private readonly ScoreSeoAction $scoreSeo) {}

    /**
     * Create a category as the last child of its parent (or of the top level).
     */
    public function handle(User $actor, CategoryData $data): Category
    {
        $category = Category::query()->create([
            'parent_id' => $data->parentId,
            'name' => $data->name,
            'slug' => $data->slug,
            'description' => $data->description,
            'meta_title' => $data->metaTitle,
            'meta_description' => $data->metaDescription,
            'focus_keyphrase' => $data->focusKeyphrase,
            'is_active' => $data->isActive,
            'position' => Category::nextPositionUnder($data->parentId),
        ]);

        $this->scoreSeo->handle($category);

        activity('admin')
            ->causedBy($actor)
            ->performedOn($category)
            ->event('category.created')
            ->withProperties(['parent_id' => $category->parent_id])
            ->log('category.created');

        return $category;
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Categories;

use App\Actions\Seo\ScoreSeoAction;
use App\Exceptions\CategoryException;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class DeleteCategoryAction
{
    public function __construct(private readonly ScoreSeoAction $scoreSeo) {}

    /**
     * Delete a category and its image. Refused while it has sub-categories: the admin
     * must move or delete them first, nothing is moved automatically.
     *
     * @throws CategoryException
     */
    public function handle(User $actor, Category $category): void
    {
        if ($category->children()->exists()) {
            throw CategoryException::hasChildren();
        }

        // Deleted products count too: orders keep referring to them.
        if (DB::table('category_product')->where('category_id', $category->id)->exists()) {
            throw CategoryException::hasProducts();
        }

        if (MenuItem::query()->where('category_id', $category->id)->exists()) {
            throw CategoryException::inMenu();
        }

        // Category names are not personal data; keeping them makes the entry readable after deletion.
        activity('admin')
            ->causedBy($actor)
            ->performedOn($category)
            ->event('category.deleted')
            ->withProperties(['name' => $category->name, 'slug' => $category->slug])
            ->log('category.deleted');

        $category->delete();

        // Categories that shared its keyphrase no longer have a duplicate.
        if ($category->focus_keyphrase !== null) {
            $this->scoreSeo->scoreSharing($category, $category->focus_keyphrase);
        }
    }
}

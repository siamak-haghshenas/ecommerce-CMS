<?php

declare(strict_types=1);

namespace App\Actions\Categories;

use App\Actions\Seo\ScoreSeoAction;
use App\Models\Category;
use App\Models\User;
use Illuminate\Http\UploadedFile;

final class UpdateCategoryImageAction
{
    public function __construct(private readonly ScoreSeoAction $scoreSeo) {}

    /**
     * Replace the category image (the collection holds a single file) and generate its conversions.
     */
    public function handle(User $actor, Category $category, UploadedFile $image): Category
    {
        $category->addMedia($image)->toMediaCollection(Category::IMAGE_COLLECTION);

        // Touching saves the category, which also clears the cached tree that holds the image URLs.
        $category->touch();

        // The image check is part of the SEO score.
        $this->scoreSeo->score($category->load('media'));

        activity('admin')
            ->causedBy($actor)
            ->performedOn($category)
            ->event('category.updated')
            ->withProperties(['fields' => ['image']])
            ->log('category.updated');

        return $category->load('media');
    }
}

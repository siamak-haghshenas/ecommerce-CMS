<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MenuItemType;
use App\Enums\MenuLocation;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuItem>
 */
class MenuItemFactory extends Factory
{
    /**
     * Define the model's default state: a link in the header menu.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'location' => MenuLocation::Header,
            'parent_id' => null,
            'position' => 0,
            'type' => MenuItemType::Url,
            'page_id' => null,
            'category_id' => null,
            'url' => 'https://example.com/'.fake()->slug(2),
            'label' => fake()->words(2, true),
            'open_in_new_tab' => false,
        ];
    }

    public function forPage(Page $page): static
    {
        return $this->state(['type' => MenuItemType::Page, 'page_id' => $page->id, 'url' => null, 'label' => null]);
    }

    public function forCategory(Category $category): static
    {
        return $this->state(['type' => MenuItemType::Category, 'category_id' => $category->id, 'url' => null, 'label' => null]);
    }
}

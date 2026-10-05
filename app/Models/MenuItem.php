<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MenuItemType;
use App\Enums\MenuLocation;
use App\Enums\PageStatus;
use App\Support\CategoryTree;
use Database\Factories\MenuItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An item of the header or footer menu: a link to a page, a category or an address. Top items may
 * have sub-items (two levels). Items whose page or category isn't visible are hidden, never removed.
 */
#[Fillable(['location', 'parent_id', 'position', 'type', 'page_id', 'category_id', 'url', 'label', 'open_in_new_tab'])]
class MenuItem extends Model
{
    /** @use HasFactory<MenuItemFactory> */
    use HasFactory;

    public const int MAX_ITEMS = 100;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'location' => MenuLocation::class,
            'type' => MenuItemType::class,
            'parent_id' => 'integer',
            'position' => 'integer',
            'page_id' => 'integer',
            'category_id' => 'integer',
            'open_in_new_tab' => 'boolean',
        ];
    }

    /**
     * Trashed pages too: their items are hidden, not lost.
     *
     * @return BelongsTo<Page, $this>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<self, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position')->orderBy('id');
    }

    /**
     * One menu's top items in order, with their sub-items and targets.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function tree(Builder $query, MenuLocation $location): void
    {
        $query->where('location', $location)
            ->whereNull('parent_id')
            ->orderBy('position')
            ->orderBy('id')
            ->with(['page', 'category', 'children.page', 'children.category']);
    }

    /**
     * Why storefronts don't show the item ("page_draft", "page_scheduled", "page_trashed",
     * "category_hidden"), or null when they do. Links are always shown.
     */
    public function hiddenReason(CategoryTree $categories): ?string
    {
        return match ($this->type) {
            MenuItemType::Page => match (true) {
                $this->page === null || $this->page->trashed() => 'page_trashed',
                $this->page->status === PageStatus::Draft => 'page_draft',
                $this->page->state() === 'scheduled' => 'page_scheduled',
                default => null,
            },
            MenuItemType::Category => $this->category_id !== null && $categories->isVisible($this->category_id) ? null : 'category_hidden',
            MenuItemType::Url => null,
        };
    }

    /**
     * The label to show: the one typed, else the page's title or the category's name.
     */
    public function displayLabel(): string
    {
        return $this->label ?? match ($this->type) {
            MenuItemType::Page => (string) $this->page?->title,
            MenuItemType::Category => (string) $this->category?->name,
            MenuItemType::Url => (string) $this->url,
        };
    }
}

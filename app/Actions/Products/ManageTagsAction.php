<?php

declare(strict_types=1);

namespace App\Actions\Products;

use App\Models\Product;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The Tags page: rename, merge duplicates, delete. Tags are shared by products and blog posts.
 * Product search text includes tag names, so the affected products are refreshed.
 */
final readonly class ManageTagsAction
{
    public function __construct(
        private ResolveTagsAction $resolveTags,
        private RefreshProductSearchTextAction $refreshSearchText,
    ) {}

    public function rename(User $actor, Tag $tag, string $name): Tag
    {
        $previous = $tag->name;
        $tag->update(['name' => $name, 'slug' => $this->resolveTags->uniqueSlug($name, $tag->id)]);
        $this->refreshProducts($tag);

        activity('admin')->causedBy($actor)->performedOn($tag)->event('tag.renamed')
            ->withProperties(['from' => $previous, 'to' => $name])->log('tag.renamed');

        return $tag;
    }

    /**
     * Move every product, post and coupon of $tag to $into, then delete $tag.
     */
    public function merge(User $actor, Tag $tag, Tag $into): Tag
    {
        DB::transaction(function () use ($tag, $into): void {
            $into->products()->syncWithoutDetaching($tag->products()->pluck('products.id')->all());
            $into->posts()->syncWithoutDetaching($tag->posts()->withTrashed()->pluck('posts.id')->all());
            // Coupons limited to the merged tag keep their items.
            $into->coupons()->syncWithoutDetaching($tag->coupons()->pluck('coupons.id')->all());
            $tag->delete();
        });

        $this->refreshProducts($into);

        activity('admin')->causedBy($actor)->performedOn($into)->event('tag.merged')
            ->withProperties(['from' => $tag->name, 'to' => $into->name])->log('tag.merged');

        return $into;
    }

    public function delete(User $actor, Tag $tag): void
    {
        $productIds = $tag->products()->pluck('products.id')->all();
        $tag->delete();
        $this->refreshSearchText->forQuery(Product::query()->whereKey($productIds));

        activity('admin')->causedBy($actor)->event('tag.deleted')
            ->withProperties(['name' => $tag->name])->log('tag.deleted');
    }

    private function refreshProducts(Tag $tag): void
    {
        $this->refreshSearchText->forQuery(Product::query()->whereHas('tags', fn ($query) => $query->whereKey($tag->id)));
    }
}

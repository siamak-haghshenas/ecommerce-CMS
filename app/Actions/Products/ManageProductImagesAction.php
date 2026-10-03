<?php

declare(strict_types=1);

namespace App\Actions\Products;

use App\Actions\Seo\ScoreSeoAction;
use App\Exceptions\CatalogException;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * The product gallery: images in order, the first one is the main image. Variants pick their
 * image from the gallery; deleting an image clears it from those variants.
 */
final readonly class ManageProductImagesAction
{
    public function __construct(private ScoreSeoAction $scoreSeo) {}

    public function add(User $actor, Product $product, UploadedFile $image): void
    {
        $product->addMedia($image)->toMediaCollection(Product::GALLERY_COLLECTION);
        $this->changed($actor, $product);
    }

    public function remove(User $actor, Product $product, Media $media): void
    {
        $media->delete();
        $this->changed($actor, $product);
    }

    /**
     * @param  list<int>  $mediaIds  every image of the gallery, in the new order
     *
     * @throws CatalogException when the ids are not exactly the product's images
     */
    public function reorder(User $actor, Product $product, array $mediaIds): void
    {
        $current = $product->getMedia(Product::GALLERY_COLLECTION)->pluck('id')->map(fn ($id): int => (int) $id)->sort()->values()->all();
        $sent = $mediaIds;
        sort($sent);

        if ($current !== $sent) {
            throw CatalogException::listChanged();
        }

        Media::setNewOrder($mediaIds);
        $this->changed($actor, $product);
    }

    private function changed(User $actor, Product $product): void
    {
        $product->touch();
        $this->scoreSeo->score($product->load('media'));

        activity('admin')
            ->causedBy($actor)
            ->performedOn($product)
            ->event('product.updated')
            ->withProperties(['name' => $product->name, 'fields' => ['images']])
            ->log('product.updated');
    }
}

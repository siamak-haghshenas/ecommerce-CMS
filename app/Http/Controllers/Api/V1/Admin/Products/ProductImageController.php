<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Products;

use App\Actions\Products\ManageProductImagesAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Catalog\ReorderIdsRequest;
use App\Http\Requests\Api\V1\Admin\Products\UploadProductImageRequest;
use App\Models\Product;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * The product gallery. Every endpoint returns the gallery in order (the first image is the main one).
 */
class ProductImageController extends Controller
{
    public function store(UploadProductImageRequest $request, Product $product, ManageProductImagesAction $images, #[CurrentUser] User $actor): JsonResponse
    {
        $images->add($actor, $product, $request->uploadedImage());

        return $this->gallery($product);
    }

    public function destroy(Product $product, Media $media, ManageProductImagesAction $images, #[CurrentUser] User $actor): JsonResponse
    {
        abort_unless(
            $media->model_type === $product->getMorphClass()
            && (int) $media->model_id === $product->id
            && $media->collection_name === Product::GALLERY_COLLECTION,
            404,
        );

        $images->remove($actor, $product, $media);

        return $this->gallery($product);
    }

    public function reorder(ReorderIdsRequest $request, Product $product, ManageProductImagesAction $images, #[CurrentUser] User $actor): JsonResponse
    {
        $images->reorder($actor, $product, $request->ids());

        return $this->gallery($product);
    }

    private function gallery(Product $product): JsonResponse
    {
        return response()->json(['data' => $product->load('media')->images()]);
    }
}

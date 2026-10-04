<?php

declare(strict_types=1);

use App\Enums\MediaType;
use App\Enums\MediaUsageRole;
use App\Models\Brand;
use App\Models\Category;
use App\Models\MediaAsset;
use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Moves the images items stored themselves (category image, brand logo, product gallery) into the
 * media library: each becomes a library file (the stored file stays where it is; its media row now
 * belongs to the file) used by its item in the same role, gallery order kept. Variant pictures
 * move from `image_media_id` (a media row) to `image_asset_id` (a library file).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->foreignId('image_asset_id')->nullable()->after('is_enabled')->constrained('media_assets')->nullOnDelete();
        });

        $sources = [
            [(new Category)->getMorphClass(), 'image', MediaUsageRole::Image],
            [(new Brand)->getMorphClass(), 'logo', MediaUsageRole::Logo],
            [(new Product)->getMorphClass(), 'gallery', MediaUsageRole::Gallery],
        ];
        $assetIdByMedia = [];

        DB::transaction(function () use ($sources, &$assetIdByMedia): void {
            foreach ($sources as [$modelType, $collection, $role]) {
                $positions = [];

                Media::query()
                    ->where('model_type', $modelType)
                    ->where('collection_name', $collection)
                    ->orderBy('model_id')
                    ->orderBy('order_column')
                    ->orderBy('id')
                    ->each(function (Media $media) use ($modelType, $role, &$positions, &$assetIdByMedia): void {
                        $path = $media->getPath();
                        [$width, $height] = is_file($path) ? (getimagesize($path) ?: [null, null]) : [null, null];

                        $assetId = DB::table('media_assets')->insertGetId([
                            'type' => MediaType::Image->value,
                            'name' => $media->file_name,
                            'mime_type' => (string) $media->mime_type,
                            'size' => (int) $media->size,
                            'width' => $width,
                            'height' => $height,
                            'created_at' => $media->created_at,
                            'updated_at' => now(),
                        ]);

                        $position = $positions[$media->model_id] ?? 0;
                        $positions[$media->model_id] = $position + 1;

                        DB::table('media_usages')->insert([
                            'media_asset_id' => $assetId,
                            'usable_type' => $modelType,
                            'usable_id' => $media->model_id,
                            'role' => $role->value,
                            'position' => $position,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        DB::table('media')->where('id', $media->id)->update([
                            'model_type' => (new MediaAsset)->getMorphClass(),
                            'model_id' => $assetId,
                            'collection_name' => MediaAsset::FILE_COLLECTION,
                        ]);

                        $assetIdByMedia[$media->id] = $assetId;
                    });
            }

            foreach ($assetIdByMedia as $mediaId => $assetId) {
                DB::table('product_variants')->where('image_media_id', $mediaId)->update(['image_asset_id' => $assetId]);
            }
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('image_media_id');
        });
    }

    /**
     * Restores the column only: the images stay in the library.
     */
    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->foreignId('image_media_id')->nullable()->after('is_enabled')->constrained('media')->nullOnDelete();
            $table->dropConstrainedForeignId('image_asset_id');
        });
    }
};

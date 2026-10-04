<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The media library: files (each stored once, with its versions in spatie's `media` table),
 * one level of folders, where each file is used, and chunked uploads in progress.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_folders', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->timestamps();
        });

        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();
            // Deleting a folder moves its files to "no folder".
            $table->foreignId('folder_id')->nullable()->constrained('media_folders')->nullOnDelete();
            $table->string('type', 20)->index();
            $table->string('name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('title')->nullable();
            $table->string('alt')->nullable();
            // A video's cover picture: another (image) file of the library.
            $table->foreignId('cover_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            // Deleted files stay in the trash (config media.trash_days), then they are removed.
            $table->softDeletes()->index();
        });

        Schema::create('media_usages', function (Blueprint $table) {
            $table->id();
            // A file in use can't be removed, so neither can its usage rows disappear with it.
            $table->foreignId('media_asset_id')->constrained()->restrictOnDelete();
            $table->morphs('usable');
            // Where on the item it is used: gallery, image, logo, variant, description, ...
            $table->string('role', 30);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['media_asset_id', 'usable_type', 'usable_id', 'role'], 'media_usages_unique');
        });

        Schema::create('media_uploads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->unsignedBigInteger('received')->default(0);
            $table->foreignId('folder_id')->nullable()->constrained('media_folders')->nullOnDelete();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_uploads');
        Schema::dropIfExists('media_usages');
        Schema::dropIfExists('media_assets');
        Schema::dropIfExists('media_folders');
    }
};

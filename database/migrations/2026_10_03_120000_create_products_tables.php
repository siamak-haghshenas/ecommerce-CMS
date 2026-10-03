<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. All amounts are whole Rials.
     */
    public function up(): void
    {
        // Store settings (price display currency, low-stock default, ...).
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->json('value');
            $table->timestamps();
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('type', 16);
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('short_description')->nullable();
            // Sanitized HTML from the rich text editor.
            $table->longText('description')->nullable();
            $table->string('status', 16);
            // A future date means "scheduled".
            $table->timestamp('published_at')->nullable();
            // Restricted: a brand with products can't be deleted.
            $table->foreignId('brand_id')->nullable()->constrained()->restrictOnDelete();
            $table->boolean('track_stock')->default(true);
            $table->boolean('allow_backorders')->default(false);
            $table->unsignedInteger('low_stock_threshold')->nullable();
            $table->unsignedInteger('weight_grams')->nullable();
            $table->unsignedInteger('length_mm')->nullable();
            $table->unsignedInteger('width_mm')->nullable();
            $table->unsignedInteger('height_mm')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->string('focus_keyphrase', 120)->nullable();
            $table->unsignedTinyInteger('seo_score')->nullable();
            $table->unsignedTinyInteger('readability_score')->nullable();
            // Normalized name, brand names, SKUs, tags and categories, for search.
            $table->text('search_text')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
        });

        Schema::create('category_product', function (Blueprint $table) {
            // Restricted: a category with products can't be deleted.
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);

            $table->primary(['category_id', 'product_id']);
            $table->index('product_id');
        });

        // The options a variable product varies by, in order (Size before Color).
        Schema::create('product_options', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('option_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('position')->default(0);

            $table->primary(['product_id', 'option_id']);
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku', 64)->unique();
            $table->unsignedBigInteger('price');
            $table->unsignedBigInteger('sale_price')->nullable();
            $table->timestamp('sale_starts_at')->nullable();
            $table->timestamp('sale_ends_at')->nullable();
            // Signed: can go below zero when backorders are allowed.
            $table->integer('stock_quantity')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->foreignId('image_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        // Which option values make up a variant (Red + XL).
        Schema::create('option_value_product_variant', function (Blueprint $table) {
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            // Restricted: a value used by a variant can't be deleted.
            $table->foreignId('option_value_id')->constrained()->restrictOnDelete();

            $table->primary(['product_variant_id', 'option_value_id']);
        });

        // A product's specifications. Several rows for one "select" attribute = several values.
        Schema::create('product_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained()->restrictOnDelete();
            $table->foreignId('attribute_value_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('value_text')->nullable();
            $table->decimal('value_number', 14, 3)->nullable();
            $table->boolean('value_boolean')->nullable();
            $table->timestamps();

            $table->index(['attribute_id', 'attribute_value_id']);
            $table->index(['attribute_id', 'value_number']);
        });

        Schema::create('product_tag', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();

            $table->primary(['product_id', 'tag_id']);
        });

        // Stock history: insert-only.
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason', 24);
            $table->integer('quantity_change');
            $table->integer('quantity_before');
            $table->integer('quantity_after');
            $table->string('note')->nullable();
            $table->nullableMorphs('reference');
            $table->timestamp('created_at')->nullable();

            $table->index(['product_variant_id', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('product_tag');
        Schema::dropIfExists('product_attribute_values');
        Schema::dropIfExists('option_value_product_variant');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('product_options');
        Schema::dropIfExists('category_product');
        Schema::dropIfExists('products');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('settings');
    }
};

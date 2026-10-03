<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Product specifications (Material, Screen size, ...), shared by all products.
        Schema::create('attributes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug')->unique();
            $table->string('type', 16);
            $table->string('unit', 20)->nullable();
            $table->boolean('is_filterable')->default(false);
            $table->unsignedInteger('position')->default(0)->index();
            $table->timestamps();
        });

        // The choices of a "select" attribute.
        Schema::create('attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->string('value', 120);
            $table->string('slug');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['attribute_id', 'slug']);
        });

        // The attributes a category's products use; sub-categories inherit them.
        Schema::create('attribute_category', function (Blueprint $table) {
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);

            $table->primary(['category_id', 'attribute_id']);
        });

        // Variant dimensions (Size, Color).
        Schema::create('options', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug')->unique();
            $table->string('display_type', 16);
            $table->unsignedInteger('position')->default(0)->index();
            $table->timestamps();
        });

        Schema::create('option_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('option_id')->constrained()->cascadeOnDelete();
            $table->string('value', 120);
            $table->string('slug');
            $table->char('color_hex', 7)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['option_id', 'slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('option_values');
        Schema::dropIfExists('options');
        Schema::dropIfExists('attribute_category');
        Schema::dropIfExists('attribute_values');
        Schema::dropIfExists('attributes');
    }
};

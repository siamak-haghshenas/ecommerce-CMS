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
        // The items of the header and footer menus: two levels (top items and their sub-items).
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->string('location', 20);
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->string('type', 20);
            // A page removed for good takes its menu items with it (trashed pages are only hidden).
            $table->foreignId('page_id')->nullable()->constrained()->cascadeOnDelete();
            // Deleting a linked category is refused by DeleteCategoryAction (category_in_menu).
            $table->foreignId('category_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('url', 2048)->nullable();
            $table->string('label', 120)->nullable();
            $table->boolean('open_in_new_tab')->default(false);
            $table->timestamps();
            $table->index(['location', 'parent_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};

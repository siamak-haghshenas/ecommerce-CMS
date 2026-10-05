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
        Schema::table('categories', function (Blueprint $table) {
            // Every existing category is a product category.
            $table->string('type', 20)->default('product')->after('id');
            // A product category and a blog category may share a slug (storefronts use different addresses).
            $table->dropUnique(['slug']);
            $table->unique(['type', 'slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique(['type', 'slug']);
            $table->unique('slug');
            $table->dropColumn('type');
        });
    }
};

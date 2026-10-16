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
        // What a coupon applies to (`applies_to` selected: categories with their sub-categories,
        // brands, tags, products) and the products it never applies to (`is_excluded`).
        // Deleting a category, brand or tag removes its rows; merging a tag moves them.
        Schema::create('coupon_targets', function (Blueprint $table) {
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->morphs('target');
            $table->boolean('is_excluded')->default(false);
            $table->primary(['coupon_id', 'target_type', 'target_id', 'is_excluded']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupon_targets');
    }
};

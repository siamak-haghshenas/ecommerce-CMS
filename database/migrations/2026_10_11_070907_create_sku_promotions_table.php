<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Promotions, rebuilt per SKU: each row is one variant's discount with its own dates, order
     * limit and number of discounted units. A variant may have several rows whose dates don't
     * overlap.
     */
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            // A percentage (1–90) or Rials off each unit.
            $table->string('discount_type', 20);
            $table->unsignedBigInteger('discount_value');
            // The most units one order may hold at this price; null: no limit.
            $table->unsignedInteger('max_per_order')->nullable();
            // How many units are sold at this price in all; null: no limit. `sold_count` is counted by orders.
            $table->unsignedInteger('quantity_limit')->nullable();
            $table->unsignedInteger('sold_count')->default(0);
            // Both required (checked on save); nullable only because MySQL won't take a NOT NULL timestamp without a default.
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['product_variant_id', 'starts_at']);
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->foreignId('promotion_id')->nullable()->after('discount_source')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('promotion_id');
        });

        Schema::dropIfExists('promotions');
    }
};

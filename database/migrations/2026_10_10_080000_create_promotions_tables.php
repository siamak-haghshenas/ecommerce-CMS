<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Automatic catalog discounts: they lower the price storefronts show.
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('type', 20);
            // A percentage (1–90) or Rials off each unit.
            $table->unsignedBigInteger('value');
            // Percent promotions only: the most Rials taken off one unit.
            $table->unsignedBigInteger('max_discount')->nullable();
            $table->string('scope', 20)->default('selected');
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('ends_at')->nullable()->index();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // What a promotion applies to (or, `is_excluded`, which products it skips).
        Schema::create('promotion_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->string('target_type', 20);
            $table->unsignedBigInteger('target_id');
            $table->boolean('is_excluded')->default(false);
            $table->index(['target_type', 'target_id']);
            $table->unique(['promotion_id', 'target_type', 'target_id', 'is_excluded'], 'promotion_targets_unique');
        });

        // The price shoppers pay now, stored so storefront filters and sorting stay in SQL.
        Schema::table('product_variants', function (Blueprint $table) {
            $table->unsignedBigInteger('current_price')->nullable()->after('sale_ends_at');
            $table->string('discount_source', 20)->nullable()->after('current_price');
            $table->foreignId('promotion_id')->nullable()->after('discount_source')->constrained()->nullOnDelete();
            // The next moment the current price may change (a sale or promotion starting or ending).
            $table->timestamp('price_recalc_at')->nullable()->index()->after('promotion_id');
        });

        // Existing variants: their sale price as it applies now; promotions don't exist yet.
        $now = now();
        DB::table('product_variants')->update([
            'current_price' => DB::raw('CASE WHEN sale_price IS NOT NULL AND sale_price < price'
                .' AND (sale_starts_at IS NULL OR sale_starts_at <= '.DB::getPdo()->quote((string) $now).')'
                .' AND (sale_ends_at IS NULL OR sale_ends_at > '.DB::getPdo()->quote((string) $now).')'
                .' THEN sale_price ELSE price END'),
            'discount_source' => DB::raw('CASE WHEN sale_price IS NOT NULL AND sale_price < price'
                .' AND (sale_starts_at IS NULL OR sale_starts_at <= '.DB::getPdo()->quote((string) $now).')'
                .' AND (sale_ends_at IS NULL OR sale_ends_at > '.DB::getPdo()->quote((string) $now).')'
                ." THEN 'sale' ELSE NULL END"),
            'price_recalc_at' => DB::raw('CASE'
                .' WHEN sale_price IS NULL THEN NULL'
                .' WHEN sale_starts_at > '.DB::getPdo()->quote((string) $now).' THEN sale_starts_at'
                .' WHEN sale_ends_at > '.DB::getPdo()->quote((string) $now).' THEN sale_ends_at'
                .' ELSE NULL END'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('promotion_id');
            $table->dropColumn(['current_price', 'discount_source', 'price_recalc_at']);
        });

        Schema::dropIfExists('promotion_targets');
        Schema::dropIfExists('promotions');
    }
};

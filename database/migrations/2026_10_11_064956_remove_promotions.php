<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Promotions are removed; sale events and the variants' own sale prices stay. Variants priced
     * by a promotion go back to their regular price and are due for a refresh, so the next
     * `prices:refresh --due` (every minute) gives them their best remaining price. Audit entries
     * about promotions stay, without their subject.
     */
    public function up(): void
    {
        DB::table('product_variants')->where('discount_source', 'promotion')->update([
            'current_price' => DB::raw('price'),
            'discount_source' => null,
            'max_per_order' => null,
            'price_recalc_at' => now(),
        ]);

        DB::table('activity_log')->where('subject_type', 'App\\Models\\Promotion')->update([
            'subject_type' => null,
            'subject_id' => null,
        ]);

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('promotion_id');
        });

        Schema::dropIfExists('promotion_targets');
        Schema::dropIfExists('product_promotion');
        Schema::dropIfExists('promotions');
    }

    /**
     * Brings the empty tables back (the promotions themselves are gone).
     */
    public function down(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('type', 20);
            $table->unsignedBigInteger('value');
            $table->unsignedBigInteger('max_discount')->nullable();
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('ends_at')->nullable()->index();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('product_promotion', function (Blueprint $table) {
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('discount_type', 20)->nullable();
            $table->unsignedBigInteger('discount_value')->nullable();
            $table->unsignedBigInteger('max_discount')->nullable();
            $table->unsignedInteger('max_per_order')->nullable();
            $table->primary(['promotion_id', 'product_id']);
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->foreignId('promotion_id')->nullable()->after('discount_source')->constrained()->nullOnDelete();
        });
    }
};

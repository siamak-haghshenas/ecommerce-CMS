<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Order limits on discounts: the most units of one variant an order may hold at the discounted
     * price (null: no limit). Set per product of a promotion and per variant of a sale event; the
     * limit of whatever gives a variant its current price is stored on the variant with the price.
     */
    public function up(): void
    {
        Schema::table('product_promotion', function (Blueprint $table) {
            $table->unsignedInteger('max_per_order')->nullable()->after('max_discount');
        });

        Schema::table('sale_event_items', function (Blueprint $table) {
            $table->unsignedInteger('max_per_order')->nullable()->after('discount_value');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->unsignedInteger('max_per_order')->nullable()->after('sale_event_id');
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', fn (Blueprint $table) => $table->dropColumn('max_per_order'));
        Schema::table('sale_event_items', fn (Blueprint $table) => $table->dropColumn('max_per_order'));
        Schema::table('product_promotion', fn (Blueprint $table) => $table->dropColumn('max_per_order'));
    }
};

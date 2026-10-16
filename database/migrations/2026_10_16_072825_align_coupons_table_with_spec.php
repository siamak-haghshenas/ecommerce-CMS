<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The column names and defaults the admin UI and the spec use: `name`, `min_order_amount`,
     * `per_customer_limit` (default 1), `exclude_discounted` (the opposite of the old
     * `applies_to_sale_items`), a nullable `value` (free shipping has none) and `applies_to`.
     */
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->renameColumn('description', 'name');
            $table->renameColumn('min_subtotal', 'min_order_amount');
            $table->renameColumn('usage_limit_per_customer', 'per_customer_limit');
            $table->boolean('exclude_discounted')->default(false)->after('applies_to_sale_items');
            // all | selected (the categories, brands, tags and products in coupon_targets, added next).
            $table->string('applies_to', 20)->default('all')->after('max_discount');
        });

        DB::table('coupons')->update(['exclude_discounted' => DB::raw('NOT applies_to_sale_items')]);
        DB::table('coupons')->whereNull('name')->update(['name' => DB::raw('code')]);

        Schema::table('coupons', function (Blueprint $table) {
            $table->dropColumn('applies_to_sale_items');
            // For staff only; never shown to customers.
            $table->string('name', 120)->nullable(false)->change();
            // Percent: 1–90. Fixed: Rials off. Free shipping: null.
            $table->unsignedBigInteger('value')->nullable()->change();
            $table->unsignedInteger('per_customer_limit')->nullable()->default(1)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->boolean('applies_to_sale_items')->default(false)->after('exclude_discounted');
        });

        DB::table('coupons')->update(['applies_to_sale_items' => DB::raw('NOT exclude_discounted')]);
        DB::table('coupons')->whereNull('value')->update(['value' => 0]);

        Schema::table('coupons', function (Blueprint $table) {
            $table->dropColumn(['exclude_discounted', 'applies_to']);
            $table->renameColumn('name', 'description');
            $table->renameColumn('min_order_amount', 'min_subtotal');
            $table->renameColumn('per_customer_limit', 'usage_limit_per_customer');
        });

        Schema::table('coupons', function (Blueprint $table) {
            $table->string('description')->nullable()->change();
            $table->unsignedBigInteger('value')->nullable(false)->change();
            $table->unsignedInteger('usage_limit_per_customer')->nullable()->default(null)->change();
        });
    }
};

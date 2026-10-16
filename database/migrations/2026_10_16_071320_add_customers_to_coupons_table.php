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
        Schema::table('coupons', function (Blueprint $table) {
            // App\Enums\CouponCustomerScope: all | selected (only the customers in coupon_customer).
            $table->string('customer_scope', 20)->default('all')->after('applies_to_sale_items');
        });

        // The customers a `selected` coupon is for. Anonymising a customer removes their rows.
        Schema::create('coupon_customer', function (Blueprint $table) {
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['coupon_id', 'user_id']);
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupon_customer');

        Schema::table('coupons', function (Blueprint $table) {
            $table->dropColumn('customer_scope');
        });
    }
};

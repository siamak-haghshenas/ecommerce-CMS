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
        // Discount codes a customer enters at checkout. Eligible products, specific customers and
        // usage history live in their own tables (added later).
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            // Stored uppercase with Latin digits, so lookups are case-insensitive.
            $table->string('code', 50)->unique();
            // Internal note for staff; never shown to customers.
            $table->string('description')->nullable();
            // App\Enums\CouponType: percent | fixed.
            $table->string('type', 20);
            // Percent: 1–100. Fixed: an amount in Rials.
            $table->unsignedBigInteger('value');
            // Cap on a percent discount, in Rials. Null = no cap.
            $table->unsignedBigInteger('max_discount')->nullable();
            // Minimum cart subtotal, in Rials. Null = no minimum.
            $table->unsignedBigInteger('min_subtotal')->nullable();
            // Null = unlimited.
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('usage_limit_per_customer')->nullable();
            // Filled once orders exist.
            $table->unsignedInteger('used_count')->default(0);
            $table->boolean('first_order_only')->default(false);
            $table->boolean('applies_to_sale_items')->default(false);
            // Null start = valid now; null end = never expires.
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('ends_at')->nullable()->index();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            // A used coupon is never removed for good.
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};

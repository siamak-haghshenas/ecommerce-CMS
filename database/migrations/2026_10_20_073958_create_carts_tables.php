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
        // A shopping cart: a customer's (one per account) or a guest's, found by the hash of the
        // token the storefront keeps. Prices are never stored: they are worked out on every read.
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            // sha256 of a guest cart's token; null for customer carts.
            $table->string('token_hash', 64)->nullable()->unique();
            // Customers only; checked again on every read.
            $table->string('coupon_code', 50)->nullable();
            // Pruned after config('cart.prune_after_days').
            $table->timestamp('last_activity_at')->index();
            $table->timestamps();
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            // The unit price (Rials) the customer last saw, to tell them it changed.
            $table->unsignedBigInteger('unit_price_seen');
            // The quantity asked for before a merge cut it to the line's limit (shown until changed).
            $table->unsignedInteger('limited_from')->nullable();
            $table->timestamps();

            $table->unique(['cart_id', 'product_variant_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
    }
};

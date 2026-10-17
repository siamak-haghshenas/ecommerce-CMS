<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Provinces and cities (fixed data, see IranLocationsSeeder) and the admin-defined shipping
     * methods with their weight or volume ranges and courier cities.
     */
    public function up(): void
    {
        Schema::create('provinces', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->unsignedSmallInteger('position')->default(0);
        });

        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('province_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);

            $table->unique(['province_id', 'name']);
        });

        Schema::create('shipping_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('description', 500)->nullable();
            // App\Enums\ShippingMethodType: weight, volume or courier.
            $table->string('type', 16);
            // The carrier collects the fee on delivery; it isn't part of the online payment.
            $table->boolean('fee_on_delivery')->default(false);
            // Courier: the fee for cities without one of their own.
            $table->unsignedBigInteger('default_fee')->nullable();
            // Weight: added once to every order's weight.
            $table->unsignedInteger('packaging_weight_grams')->nullable();
            $table->unsignedBigInteger('min_order_amount')->nullable();
            $table->unsignedBigInteger('free_over_amount')->nullable();
            $table->unsignedSmallInteger('delivery_days_min')->nullable();
            $table->unsignedSmallInteger('delivery_days_max')->nullable();
            $table->boolean('is_active')->default(false);
            // The order customers see the methods in at checkout.
            $table->unsignedInteger('position')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            // Orders will keep pointing at deleted methods (they also copy the name and fee).
            $table->softDeletes();
        });

        // Weight methods: grams; volume methods: cubic centimetres. range_start is included,
        // range_end excluded (null: no upper limit, last range only).
        Schema::create('shipping_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_method_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('range_start');
            $table->unsignedBigInteger('range_end')->nullable();
            $table->unsignedBigInteger('fee');

            $table->index(['shipping_method_id', 'range_start']);
        });

        Schema::create('city_shipping_method', function (Blueprint $table) {
            $table->foreignId('shipping_method_id')->constrained()->cascadeOnDelete();
            $table->foreignId('city_id')->constrained()->cascadeOnDelete();
            // Null: the method's default fee.
            $table->unsignedBigInteger('fee')->nullable();

            $table->primary(['shipping_method_id', 'city_id']);
            $table->index('city_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('city_shipping_method');
        Schema::dropIfExists('shipping_rates');
        Schema::dropIfExists('shipping_methods');
        Schema::dropIfExists('cities');
        Schema::dropIfExists('provinces');
    }
};

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
        // Time-limited sales on chosen variants, with an optional landing page for storefronts.
        Schema::create('sale_events', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            // Always set (the save request requires both); nullable only because MariaDB gives a
            // NOT NULL timestamp an automatic default.
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('ends_at')->nullable()->index();
            $table->boolean('is_active')->default(true);
            // Priority: 0 is the highest. Of the events running for a variant, the highest is used.
            $table->unsignedInteger('position')->default(0)->index();
            $table->boolean('has_landing_page')->default(false);
            $table->string('slug', 120)->nullable()->unique();
            $table->string('landing_title')->nullable();
            $table->longText('landing_description')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->string('focus_keyphrase', 120)->nullable();
            $table->unsignedTinyInteger('seo_score')->nullable();
            $table->unsignedTinyInteger('readability_score')->nullable();
            $table->boolean('robots_noindex')->default(false);
            $table->string('canonical_url', 2048)->nullable();
            $table->string('og_title')->nullable();
            $table->string('og_description', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // One row per variant: its discount in the event and an optional narrower time window.
        Schema::create('sale_event_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->string('discount_type', 20);
            // A percentage (1–90) or Rials off each unit.
            $table->unsignedBigInteger('discount_value');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unique(['sale_event_id', 'product_variant_id']);
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->foreignId('sale_event_id')->nullable()->after('promotion_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Prices given by an event go back to the regular price until `prices:refresh --due` reprices them.
        DB::table('product_variants')->where('discount_source', 'sale_event')->update([
            'current_price' => DB::raw('price'),
            'discount_source' => null,
            'price_recalc_at' => now(),
        ]);

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sale_event_id');
        });

        Schema::dropIfExists('sale_event_items');
        Schema::dropIfExists('sale_events');
    }
};

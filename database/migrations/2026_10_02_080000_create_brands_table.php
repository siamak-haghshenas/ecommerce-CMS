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
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('website')->nullable();
            // Manual order, set by drag and drop in the admin panel.
            $table->unsignedInteger('position')->default(0)->index();
            $table->boolean('is_active')->default(true);
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->string('focus_keyphrase', 120)->nullable();
            // Stored on every save by ScoreSeoAction; null until analysed.
            $table->unsignedTinyInteger('seo_score')->nullable();
            $table->unsignedTinyInteger('readability_score')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('brands');
    }
};

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
        // One row per AI call, to follow usage and cost. The prompt and answer are never stored.
        Schema::create('ai_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('feature', 64);
            $table->nullableMorphs('subject');
            $table->string('driver', 32);
            $table->string('model', 100)->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->string('status', 16);
            $table->string('error_code', 64)->nullable();
            $table->timestamps();

            $table->index(['feature', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_requests');
    }
};

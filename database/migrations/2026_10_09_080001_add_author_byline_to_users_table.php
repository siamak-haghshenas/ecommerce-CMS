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
        // A staff member's optional public byline for blog posts (the account name is never shown).
        Schema::table('users', function (Blueprint $table) {
            $table->string('author_name', 120)->nullable();
            $table->string('author_slug', 120)->nullable()->unique();
            $table->string('author_bio', 1000)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['author_slug']);
            $table->dropColumn(['author_name', 'author_slug', 'author_bio']);
        });
    }
};

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
        Schema::table('pages', function (Blueprint $table) {
            $table->boolean('robots_noindex')->default(false)->after('focus_keyphrase');
            $table->string('canonical_url', 2048)->nullable()->after('robots_noindex');
            $table->string('og_title')->nullable()->after('canonical_url');
            $table->string('og_description', 500)->nullable()->after('og_title');
        });

        Schema::table('page_revisions', function (Blueprint $table) {
            $table->boolean('robots_noindex')->default(false)->after('focus_keyphrase');
            $table->string('canonical_url', 2048)->nullable()->after('robots_noindex');
            $table->string('og_title')->nullable()->after('canonical_url');
            $table->string('og_description', 500)->nullable()->after('og_title');
            // The Open Graph image then: a library file id (not a foreign key, the file may be gone).
            $table->unsignedBigInteger('og_image_id')->nullable()->after('og_description');
        });

        // The unsaved form of a page, one per page and staff member. Never shown on storefronts.
        Schema::create('page_autosaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->string('slug', 120)->nullable();
            $table->string('excerpt', 500)->nullable();
            $table->longText('content')->nullable();
            $table->unsignedBigInteger('image_id')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->string('focus_keyphrase', 120)->nullable();
            $table->boolean('robots_noindex')->default(false);
            $table->string('canonical_url', 2048)->nullable();
            $table->string('og_title')->nullable();
            $table->string('og_description', 500)->nullable();
            $table->unsignedBigInteger('og_image_id')->nullable();
            $table->timestamps();
            $table->unique(['page_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('page_autosaves');

        Schema::table('page_revisions', function (Blueprint $table) {
            $table->dropColumn(['robots_noindex', 'canonical_url', 'og_title', 'og_description', 'og_image_id']);
        });

        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn(['robots_noindex', 'canonical_url', 'og_title', 'og_description']);
        });
    }
};

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
        Schema::table('categories', function (Blueprint $table) {
            $table->string('focus_keyphrase', 120)->nullable()->after('meta_description');
            // Stored on every save by ScoreCategorySeoAction; null until analysed.
            $table->unsignedTinyInteger('seo_score')->nullable()->after('focus_keyphrase');
            $table->unsignedTinyInteger('readability_score')->nullable()->after('seo_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['focus_keyphrase', 'seo_score', 'readability_score']);
        });
    }
};

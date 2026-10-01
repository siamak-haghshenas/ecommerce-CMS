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
        Schema::table('users', function (Blueprint $table) {
            // Anonymised customers keep their row (orders reference it) but lose their phone number.
            $table->string('phone_number', 11)->nullable()->change();
            $table->timestamp('last_login_at')->nullable()->after('is_active');
            $table->timestamp('anonymised_at')->nullable()->after('last_login_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['last_login_at', 'anonymised_at']);
            $table->string('phone_number', 11)->nullable(false)->change();
        });
    }
};

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
        Schema::table('brands', function (Blueprint $table) {
            // How Persian-speaking shoppers write the brand, e.g. "آدیداس" for "Adidas". Optional.
            $table->string('persian_name', 120)->nullable()->unique()->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->dropUnique(['persian_name']);
            $table->dropColumn('persian_name');
        });
    }
};

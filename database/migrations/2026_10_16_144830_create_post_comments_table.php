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
        // Customers' comments on blog posts. Only approved ones are public; staff moderate the rest.
        // A post removed for good takes its comments; anonymising a customer deletes theirs.
        Schema::create('post_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            // The customer; linked for moderation only, never shown publicly.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('display_name', 60);
            // Plain text.
            $table->text('body');
            // App\Enums\CommentStatus: pending | approved | rejected | spam.
            $table->string('status', 20)->default('pending')->index();
            // The last status change.
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();
            // Staff only; kept while rejected.
            $table->string('rejection_reason', 500)->nullable();
            // The store's answer, shown under the comment (never a staff name).
            $table->text('reply')->nullable();
            $table->foreignId('replied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('replied_at')->nullable();
            $table->timestamps();
            $table->index(['post_id', 'status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('post_comments');
    }
};

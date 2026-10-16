<?php

declare(strict_types=1);

use App\Models\Post;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Comments belong to blog posts or products: `post_comments` becomes `comments`, linked through
     * `commentable` (existing comments keep their post). Products get the same "allow comments"
     * switch as posts. A commented item removed for good takes its comments (App\Models\Concerns\HasComments).
     */
    public function up(): void
    {
        Schema::table('post_comments', function (Blueprint $table) {
            $table->dropForeign(['post_id']);
            $table->dropIndex(['post_id', 'status', 'created_at']);
            $table->string('commentable_type')->nullable()->after('id');
            $table->unsignedBigInteger('commentable_id')->nullable()->after('commentable_type');
        });

        DB::table('post_comments')->update(['commentable_type' => Post::class, 'commentable_id' => DB::raw('post_id')]);

        Schema::table('post_comments', function (Blueprint $table) {
            $table->dropColumn('post_id');
            $table->string('commentable_type')->nullable(false)->change();
            $table->unsignedBigInteger('commentable_id')->nullable(false)->change();
            $table->index(['commentable_type', 'commentable_id', 'status', 'created_at'], 'comments_commentable_status_index');
        });

        Schema::rename('post_comments', 'comments');

        Schema::table('products', function (Blueprint $table) {
            $table->boolean('comments_enabled')->default(true)->after('allow_backorders');
        });
    }

    /**
     * Reverse the migrations. Product comments are removed: the old table only knew posts.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('comments_enabled');
        });

        DB::table('comments')->where('commentable_type', '!=', Post::class)->delete();

        Schema::rename('comments', 'post_comments');

        Schema::table('post_comments', function (Blueprint $table) {
            $table->dropIndex('comments_commentable_status_index');
            $table->unsignedBigInteger('post_id')->nullable()->after('id');
        });

        DB::table('post_comments')->update(['post_id' => DB::raw('commentable_id')]);

        Schema::table('post_comments', function (Blueprint $table) {
            $table->dropColumn(['commentable_type', 'commentable_id']);
            $table->foreign('post_id')->references('id')->on('posts')->cascadeOnDelete();
            $table->index(['post_id', 'status', 'created_at']);
        });

        Schema::table('post_comments', function (Blueprint $table) {
            $table->unsignedBigInteger('post_id')->nullable(false)->change();
        });
    }
};

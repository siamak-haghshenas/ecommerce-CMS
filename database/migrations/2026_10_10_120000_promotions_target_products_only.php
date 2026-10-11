<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Promotions now apply to a list of chosen products, each optionally with its own discount. Each
     * existing promotion keeps the products its targets reached (all products, categories with
     * their sub-categories, brands, tags and chosen products, minus excluded ones), with the
     * promotion's discount, so no price changes.
     */
    public function up(): void
    {
        Schema::create('product_promotion', function (Blueprint $table) {
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            // The product's own discount; all three null: the promotion's discount.
            $table->string('discount_type', 20)->nullable();
            $table->unsignedBigInteger('discount_value')->nullable();
            $table->unsignedBigInteger('max_discount')->nullable();
            $table->primary(['promotion_id', 'product_id']);
        });

        $children = DB::table('categories')->whereNotNull('parent_id')->get(['id', 'parent_id'])
            ->groupBy('parent_id')
            ->map(fn ($rows) => $rows->pluck('id')->all())
            ->all();
        $withDescendants = function (array $ids) use ($children): array {
            $all = [];

            while ($ids !== []) {
                $id = array_pop($ids);

                if (! isset($all[$id])) {
                    $all[$id] = true;
                    array_push($ids, ...($children[$id] ?? []));
                }
            }

            return array_keys($all);
        };

        foreach (DB::table('promotions')->get(['id', 'scope']) as $promotion) {
            $targets = DB::table('promotion_targets')->where('promotion_id', $promotion->id)->get();
            $ids = fn (string $type, bool $excluded = false): array => $targets
                ->filter(fn ($target): bool => $target->target_type === $type && (bool) $target->is_excluded === $excluded)
                ->pluck('target_id')
                ->map(fn ($id): int => (int) $id)
                ->all();
            $live = fn () => DB::table('products')->whereNull('deleted_at');

            $productIds = $ids('product');

            if ($promotion->scope === 'all') {
                $productIds = [...$productIds, ...$live()->pluck('id')->all()];
            }

            if (($categoryIds = $ids('category')) !== []) {
                $productIds = [...$productIds, ...$live()->whereIn('id', DB::table('category_product')
                    ->whereIn('category_id', $withDescendants($categoryIds))->select('product_id'))->pluck('id')->all()];
            }

            if (($brandIds = $ids('brand')) !== []) {
                $productIds = [...$productIds, ...$live()->whereIn('brand_id', $brandIds)->pluck('id')->all()];
            }

            if (($tagIds = $ids('tag')) !== []) {
                $productIds = [...$productIds, ...$live()->whereIn('id', DB::table('product_tag')
                    ->whereIn('tag_id', $tagIds)->select('product_id'))->pluck('id')->all()];
            }

            $productIds = array_diff(array_unique(array_map(intval(...), $productIds)), $ids('product', true));

            foreach (array_chunk(array_values($productIds), 500) as $chunk) {
                DB::table('product_promotion')->insert(array_map(
                    fn (int $productId): array => ['promotion_id' => $promotion->id, 'product_id' => $productId],
                    $chunk,
                ));
            }
        }

        Schema::dropIfExists('promotion_targets');

        Schema::table('promotions', function (Blueprint $table) {
            $table->dropColumn('scope');
        });
    }

    /**
     * Reverse the migrations: each promotion's products become chosen-product targets.
     */
    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->string('scope', 20)->default('selected')->after('max_discount');
        });

        Schema::create('promotion_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->string('target_type', 20);
            $table->unsignedBigInteger('target_id');
            $table->boolean('is_excluded')->default(false);
            $table->index(['target_type', 'target_id']);
            $table->unique(['promotion_id', 'target_type', 'target_id', 'is_excluded'], 'promotion_targets_unique');
        });

        DB::table('product_promotion')->orderBy('promotion_id')->orderBy('product_id')->chunk(500, function ($rows): void {
            DB::table('promotion_targets')->insert($rows->map(fn ($row): array => [
                'promotion_id' => $row->promotion_id,
                'target_type' => 'product',
                'target_id' => $row->product_id,
                'is_excluded' => false,
            ])->all());
        });

        Schema::dropIfExists('product_promotion');
    }
};

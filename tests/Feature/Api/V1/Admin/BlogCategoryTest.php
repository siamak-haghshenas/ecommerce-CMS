<?php

declare(strict_types=1);

use App\Enums\CategoryType;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\User;

use function Pest\Laravel\actingAs;

function blogStaff(UserRole $role = UserRole::Author): User
{
    return User::factory()->withRole($role)->create();
}

/**
 * @return array<string, mixed>
 */
function blogCategoryPayload(array $overrides = []): array
{
    return [
        'type' => 'blog',
        'name' => 'اخبار',
        'slug' => 'news',
        'parent_id' => null,
        'description' => null,
        'meta_title' => null,
        'meta_description' => null,
        'is_active' => true,
        ...$overrides,
    ];
}

describe('access', function () {
    it('lets authors manage blog categories but not product categories', function () {
        $author = blogStaff();
        $product = Category::factory()->create();

        $blog = actingAs($author)->postJson(route('api.v1.admin.categories.store'), blogCategoryPayload())
            ->assertCreated()
            ->assertJsonPath('data.type', 'blog')
            ->json('data.id');

        actingAs($author)->getJson(route('api.v1.admin.categories.index', ['type' => 'blog']))->assertOk()->assertJsonPath('data.0.id', $blog);
        actingAs($author)->putJson(route('api.v1.admin.categories.update', $blog), blogCategoryPayload(['name' => 'تازه‌ها']))->assertOk();

        actingAs($author)->getJson(route('api.v1.admin.categories.index'))->assertForbidden();
        actingAs($author)->getJson(route('api.v1.admin.categories.show', $product))->assertForbidden();
        actingAs($author)->postJson(route('api.v1.admin.categories.store'), blogCategoryPayload(['type' => 'product']))->assertForbidden();
        actingAs($author)->deleteJson(route('api.v1.admin.categories.destroy', $product))->assertForbidden();

        actingAs($author)->deleteJson(route('api.v1.admin.categories.destroy', $blog))->assertNoContent();
    });

    it('lets admins manage both types, each listed on its own', function () {
        $product = Category::factory()->create();
        $blog = Category::factory()->blog()->create();

        actingAs(blogStaff(UserRole::Admin))->getJson(route('api.v1.admin.categories.index'))->assertJsonPath('data.*.id', [$product->id]);
        actingAs(blogStaff(UserRole::Admin))->getJson(route('api.v1.admin.categories.index', ['type' => 'blog']))->assertJsonPath('data.*.id', [$blog->id]);
    });
});

describe('rules', function () {
    it('keeps slugs unique per type', function () {
        Category::factory()->create(['slug' => 'news']);
        $admin = blogStaff(UserRole::Admin);

        actingAs($admin)->postJson(route('api.v1.admin.categories.store'), blogCategoryPayload())->assertCreated();
        actingAs($admin)->postJson(route('api.v1.admin.categories.store'), blogCategoryPayload(['name' => 'Other']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slug');
    });

    it('never mixes types in a tree', function () {
        $productParent = Category::factory()->create();
        $blog = Category::factory()->blog()->create();
        $admin = blogStaff(UserRole::Admin);

        actingAs($admin)->postJson(route('api.v1.admin.categories.store'), blogCategoryPayload(['parent_id' => $productParent->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('parent_id');

        actingAs($admin)->putJson(route('api.v1.admin.categories.order'), ['type' => 'product', 'parent_id' => $productParent->id, 'ids' => [$blog->id]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ids.0');
    });

    it('keeps a category type when it is updated', function () {
        $blog = Category::factory()->blog()->create(['slug' => 'news']);

        actingAs(blogStaff())->putJson(route('api.v1.admin.categories.update', $blog), blogCategoryPayload(['type' => 'product']))->assertOk();

        expect($blog->fresh()?->type)->toBe(CategoryType::Blog);
    });

    it('reorders the top level of one type without touching the other', function () {
        $product = Category::factory()->create(['position' => 0]);
        $first = Category::factory()->blog()->create(['position' => 0]);
        $second = Category::factory()->blog()->create(['position' => 1]);

        actingAs(blogStaff())->putJson(route('api.v1.admin.categories.order'), ['type' => 'blog', 'parent_id' => null, 'ids' => [$second->id, $first->id]])
            ->assertOk()
            ->assertJsonPath('data.*.id', [$second->id, $first->id]);

        expect($product->fresh()?->position)->toBe(0);
    });

    it('gives blog categories no specifications and no products', function () {
        $blog = Category::factory()->blog()->create();
        $admin = blogStaff(UserRole::Admin);

        actingAs($admin)->getJson(route('api.v1.admin.categories.attributes.show', $blog))->assertNotFound();
        actingAs($admin)->putJson(route('api.v1.admin.categories.attributes.update', $blog), ['ids' => []])->assertNotFound();
        actingAs($admin)->postJson(route('api.v1.admin.products.store'), ['type' => 'simple', 'category_ids' => [$blog->id]])
            ->assertJsonValidationErrors('category_ids.0');
    });
});

describe('seo', function () {
    it('flags a repeated focus keyphrase only among categories of the same type', function () {
        $admin = blogStaff(UserRole::Admin);
        Category::factory()->create(['focus_keyphrase' => 'کفش ورزشی']);

        actingAs($admin)->postJson(route('api.v1.admin.categories.store'), blogCategoryPayload(['focus_keyphrase' => 'کفش ورزشی']))->assertCreated();
        $blog = Category::query()->where('type', CategoryType::Blog)->sole();
        $alone = $blog->seo_score;

        actingAs($admin)->postJson(route('api.v1.admin.categories.store'), blogCategoryPayload(['slug' => 'news-2', 'focus_keyphrase' => 'کفش ورزشی']))->assertCreated();

        expect($blog->fresh()?->seo_score)->toBeLessThan($alone);
    });

    it('lets authors ask AI for blog category suggestions, not product ones', function () {
        ai()->respondWith([
            'keyphrases' => ['اخبار فروشگاه', 'تازه‌های فروشگاه', 'خبرهای جدید'],
            'titles' => ['اخبار فروشگاه', 'تازه‌ترین خبرها', 'خبرهای جدید فروشگاه'],
            'meta_descriptions' => ['توضیح اول', 'توضیح دوم', 'توضیح سوم'],
        ]);

        actingAs(blogStaff())->postJson(route('api.v1.admin.seo-suggestions'), ['type' => 'blog_category', 'name' => 'اخبار'])->assertOk();
        actingAs(blogStaff())->postJson(route('api.v1.admin.seo-suggestions'), ['type' => 'category', 'name' => 'Shirts'])->assertForbidden();
    });
});

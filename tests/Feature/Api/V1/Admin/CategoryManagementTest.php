<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\withHeader;

function categoryManager(UserRole $role = UserRole::Admin): User
{
    return User::factory()->withRole($role)->create();
}

/**
 * @return array<string, mixed>
 */
function categoryPayload(array $overrides = []): array
{
    return [
        'name' => 'پیراهن مردانه',
        'slug' => 'mens-shirts',
        'parent_id' => null,
        'description' => 'Cotton and linen shirts.',
        'meta_title' => 'Men shirts',
        'meta_description' => 'Buy men shirts online.',
        'is_active' => true,
        ...$overrides,
    ];
}

function categoryEvent(string $event): Activity
{
    return Activity::query()->where('event', $event)->sole();
}

describe('access', function () {
    it('returns 401 to guests', function () {
        getJson(route('api.v1.admin.categories.index'))->assertUnauthorized();
    });

    it('returns 403 to roles without the category permissions', function (UserRole $role, string $method, string $routeName) {
        $category = Category::factory()->create();

        actingAs(categoryManager($role))
            ->json($method, route($routeName, ['category' => $category]), categoryPayload())
            ->assertForbidden();
    })->with([
        'author viewing the tree' => [UserRole::Author, 'GET', 'api.v1.admin.categories.index'],
        'author creating' => [UserRole::Author, 'POST', 'api.v1.admin.categories.store'],
        'author deleting' => [UserRole::Author, 'DELETE', 'api.v1.admin.categories.destroy'],
        'customer viewing the tree' => [UserRole::Customer, 'GET', 'api.v1.admin.categories.index'],
    ]);

    it('lets admins and super admins manage categories', function (UserRole $role) {
        actingAs(categoryManager($role))
            ->postJson(route('api.v1.admin.categories.store'), categoryPayload())
            ->assertCreated();
    })->with([UserRole::Admin, UserRole::SuperAdmin]);
});

describe('tree', function () {
    it('returns every category nested and in position order, hidden ones included', function () {
        $clothing = Category::factory()->create(['position' => 1, 'name' => 'Clothing']);
        $books = Category::factory()->create(['position' => 0, 'name' => 'Books']);
        $shirts = Category::factory()->childOf($clothing, 1)->create(['name' => 'Shirts']);
        $hats = Category::factory()->childOf($clothing, 0)->hidden()->create(['name' => 'Hats']);
        $caps = Category::factory()->childOf($hats)->create(['name' => 'Caps']);

        actingAs(categoryManager())
            ->getJson(route('api.v1.admin.categories.index'))
            ->assertOk()
            ->assertJsonPath('data.*.id', [$books->id, $clothing->id])
            ->assertJsonPath('data.1.children.*.id', [$hats->id, $shirts->id])
            ->assertJsonPath('data.1.children.0.is_active', false)
            ->assertJsonPath('data.1.children.0.children.0.id', $caps->id)
            ->assertJsonPath('data.1.children.0.children.0.is_active', true)
            ->assertJsonPath('data.1.children.0.children.0.is_visible', false)
            ->assertJsonPath('data.1.children.1.is_visible', true);
    });

    it('shows one category with its ancestors', function () {
        $clothing = Category::factory()->create();
        $men = Category::factory()->childOf($clothing)->create();
        $shirts = Category::factory()->childOf($men)->create();

        actingAs(categoryManager())
            ->getJson(route('api.v1.admin.categories.show', ['category' => $shirts]))
            ->assertOk()
            ->assertJsonPath('data.id', $shirts->id)
            ->assertJsonPath('data.ancestors.*.id', [$clothing->id, $men->id])
            ->assertJsonPath('data.children_count', 0);
    });
});

describe('create', function () {
    it('creates a category last among its siblings and logs it', function () {
        $parent = Category::factory()->create();
        Category::factory()->childOf($parent, 4)->create();
        $admin = categoryManager();

        actingAs($admin)
            ->postJson(route('api.v1.admin.categories.store'), categoryPayload(['parent_id' => $parent->id, 'slug' => ' Mens-Shirts ']))
            ->assertCreated()
            ->assertJsonPath('data.name', 'پیراهن مردانه')
            ->assertJsonPath('data.slug', 'mens-shirts')
            ->assertJsonPath('data.parent_id', $parent->id)
            ->assertJsonPath('data.position', 5);

        $entry = categoryEvent('category.created');

        expect($entry->causer_id)->toBe($admin->id)
            ->and($entry->subject_id)->toBe(Category::query()->where('slug', 'mens-shirts')->value('id'));
    });

    it('shows the new category in the tree straight away', function () {
        $admin = categoryManager();
        actingAs($admin)->getJson(route('api.v1.admin.categories.index'))->assertJsonCount(0, 'data');

        actingAs($admin)->postJson(route('api.v1.admin.categories.store'), categoryPayload())->assertCreated();

        actingAs($admin)->getJson(route('api.v1.admin.categories.index'))->assertJsonPath('data.0.slug', 'mens-shirts');
    });

    it('rejects slugs that are not lowercase Latin words joined by single hyphens', function (string $slug) {
        withHeader('Accept-Language', 'en')
            ->actingAs(categoryManager())
            ->postJson(route('api.v1.admin.categories.store'), categoryPayload(['slug' => $slug]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug' => 'Use only lowercase English letters, digits and hyphens, such as mens-shirts.']);
    })->with([
        'persian' => 'پیراهن-مردانه',
        'spaces' => 'mens shirts',
        'double hyphen' => 'mens--shirts',
        'leading hyphen' => '-mens',
        'underscore' => 'mens_shirts',
    ]);

    it('rejects a slug another category already uses', function () {
        Category::factory()->create(['slug' => 'mens-shirts']);

        withHeader('Accept-Language', 'en')
            ->actingAs(categoryManager())
            ->postJson(route('api.v1.admin.categories.store'), categoryPayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug' => 'This slug is already in use. Choose another one.']);
    });

    it('rejects an unknown parent and missing required fields', function () {
        actingAs(categoryManager())
            ->postJson(route('api.v1.admin.categories.store'), ['parent_id' => 999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'slug', 'parent_id', 'is_active']);
    });
});

describe('update', function () {
    it('updates the fields and logs only the changed field names', function () {
        $category = Category::factory()->create(['name' => 'Old', 'slug' => 'old', 'is_active' => true]);

        actingAs(categoryManager())
            ->putJson(route('api.v1.admin.categories.update', ['category' => $category]), categoryPayload([
                'name' => 'New',
                'slug' => 'old',
                'description' => null,
                'meta_title' => null,
                'meta_description' => null,
                'is_active' => false,
            ]))
            ->assertOk()
            ->assertJsonPath('data.name', 'New')
            ->assertJsonPath('data.is_active', false);

        expect(categoryEvent('category.updated')->properties->all())->toBe(['fields' => ['name', 'is_active']]);
    });

    it('does not log anything when nothing changed', function () {
        $category = Category::factory()->create(categoryPayload());

        actingAs(categoryManager())
            ->putJson(route('api.v1.admin.categories.update', ['category' => $category]), categoryPayload())
            ->assertOk();

        expect(Activity::query()->count())->toBe(0);
    });

    it('moves a category to the end of its new parent and logs the move', function () {
        $from = Category::factory()->create();
        $to = Category::factory()->create();
        Category::factory()->childOf($to, 2)->create();
        $category = Category::factory()->childOf($from)->create(categoryPayload(['parent_id' => $from->id]));

        actingAs(categoryManager())
            ->putJson(route('api.v1.admin.categories.update', ['category' => $category]), categoryPayload(['parent_id' => $to->id]))
            ->assertOk()
            ->assertJsonPath('data.parent_id', $to->id)
            ->assertJsonPath('data.position', 3);

        expect(categoryEvent('category.moved')->properties->all())->toBe(['from' => $from->id, 'to' => $to->id])
            ->and(Activity::query()->where('event', 'category.updated')->exists())->toBeFalse();
    });

    it('refuses to move a category under itself or one of its descendants', function (string $target) {
        $clothing = Category::factory()->create(['slug' => 'clothing']);
        $men = Category::factory()->childOf($clothing)->create();
        $shirts = Category::factory()->childOf($men)->create();
        $parentId = ['itself' => $clothing->id, 'child' => $men->id, 'grandchild' => $shirts->id][$target];

        withHeader('Accept-Language', 'en')
            ->actingAs(categoryManager())
            ->putJson(route('api.v1.admin.categories.update', ['category' => $clothing]), categoryPayload([
                'slug' => 'clothing',
                'parent_id' => $parentId,
            ]))
            ->assertUnprocessable()
            ->assertJsonPath('code', 'category_invalid_parent')
            ->assertJsonValidationErrors(['parent_id' => 'A category cannot be placed under itself or one of its sub-categories.']);

        expect($clothing->refresh()->parent_id)->toBeNull();
    })->with(['itself', 'child', 'grandchild']);
});

describe('delete', function () {
    it('deletes a category without sub-categories and keeps its name in the log', function () {
        $category = Category::factory()->create(['name' => 'Hats', 'slug' => 'hats']);

        actingAs(categoryManager())
            ->deleteJson(route('api.v1.admin.categories.destroy', ['category' => $category]))
            ->assertNoContent();

        expect(Category::query()->count())->toBe(0)
            ->and(categoryEvent('category.deleted')->properties->all())->toBe(['name' => 'Hats', 'slug' => 'hats']);
    });

    it('refuses to delete a category with sub-categories and moves nothing', function () {
        $parent = Category::factory()->create();
        $child = Category::factory()->childOf($parent)->create();

        withHeader('Accept-Language', 'en')
            ->actingAs(categoryManager())
            ->deleteJson(route('api.v1.admin.categories.destroy', ['category' => $parent]))
            ->assertConflict()
            ->assertExactJson([
                'message' => 'This category has sub-categories. Move or delete them first.',
                'code' => 'category_has_children',
            ]);

        expect(Category::query()->count())->toBe(2)
            ->and($child->refresh()->parent_id)->toBe($parent->id);
    });

    it('returns 404 for an unknown category', function () {
        actingAs(categoryManager())
            ->deleteJson(route('api.v1.admin.categories.destroy', ['category' => 999]))
            ->assertNotFound();
    });
});

describe('order', function () {
    it('saves the new order of a parent and logs it once', function () {
        $parent = Category::factory()->create();
        $a = Category::factory()->childOf($parent, 0)->create();
        $b = Category::factory()->childOf($parent, 1)->create();
        $c = Category::factory()->childOf($parent, 2)->create();

        actingAs(categoryManager())
            ->putJson(route('api.v1.admin.categories.order'), ['parent_id' => $parent->id, 'ids' => [$c->id, $a->id, $b->id]])
            ->assertOk()
            ->assertJsonPath('data.0.children.*.id', [$c->id, $a->id, $b->id]);

        $entry = categoryEvent('category.reordered');

        expect($entry->subject_id)->toBe($parent->id)
            ->and($entry->properties->all())->toBe(['parent_id' => $parent->id]);
    });

    it('moves a category into another parent at the dropped position', function () {
        $first = Category::factory()->create(['position' => 0]);
        $second = Category::factory()->create(['position' => 1]);
        $existing = Category::factory()->childOf($second)->create();

        actingAs(categoryManager())
            ->putJson(route('api.v1.admin.categories.order'), ['parent_id' => $second->id, 'ids' => [$first->id, $existing->id]])
            ->assertOk()
            ->assertJsonPath('data.*.id', [$second->id])
            ->assertJsonPath('data.0.children.*.id', [$first->id, $existing->id]);

        expect(categoryEvent('category.moved')->properties->all())->toBe(['from' => null, 'to' => $second->id]);
    });

    it('reorders the top level', function () {
        $a = Category::factory()->create(['position' => 0]);
        $b = Category::factory()->create(['position' => 1]);

        actingAs(categoryManager())
            ->putJson(route('api.v1.admin.categories.order'), ['parent_id' => null, 'ids' => [$b->id, $a->id]])
            ->assertOk()
            ->assertJsonPath('data.*.id', [$b->id, $a->id]);

        expect(categoryEvent('category.reordered')->subject_id)->toBeNull();
    });

    it('returns 409 when the order leaves out a current child', function () {
        $parent = Category::factory()->create();
        $a = Category::factory()->childOf($parent, 0)->create();
        $b = Category::factory()->childOf($parent, 1)->create();

        actingAs(categoryManager())
            ->putJson(route('api.v1.admin.categories.order'), ['parent_id' => $parent->id, 'ids' => [$b->id]])
            ->assertConflict()
            ->assertJsonPath('code', 'category_tree_changed');

        expect($a->refresh()->position)->toBe(0)->and($b->refresh()->position)->toBe(1);
    });

    it('refuses to move a category into its own branch', function () {
        $parent = Category::factory()->create();
        $child = Category::factory()->childOf($parent)->create();

        actingAs(categoryManager())
            ->putJson(route('api.v1.admin.categories.order'), ['parent_id' => $child->id, 'ids' => [$parent->id]])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'category_invalid_parent');

        expect($parent->refresh()->parent_id)->toBeNull();
    });

    it('validates the order payload', function () {
        actingAs(categoryManager())
            ->putJson(route('api.v1.admin.categories.order'), ['ids' => [999, 999]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['parent_id', 'ids.0', 'ids.1']);
    });
});

describe('image', function () {
    beforeEach(fn () => Storage::fake('public'));

    it('uploads an image, generates the conversions and replaces the previous image', function () {
        $category = Category::factory()->create();
        $admin = categoryManager();

        actingAs($admin)
            ->postJson(route('api.v1.admin.categories.image.store', ['category' => $category]), [
                'image' => UploadedFile::fake()->image('old.jpg', 600, 400),
            ])
            ->assertOk();

        $response = actingAs($admin)
            ->postJson(route('api.v1.admin.categories.image.store', ['category' => $category]), [
                'image' => UploadedFile::fake()->image('shirts.png', 1200, 800),
            ])
            ->assertOk();

        $media = $category->refresh()->getMedia(Category::IMAGE_COLLECTION);

        expect($media)->toHaveCount(1)
            ->and($media->first()?->file_name)->toBe('shirts.png')
            ->and($media->first()?->hasGeneratedConversion('thumb'))->toBeTrue()
            ->and($media->first()?->hasGeneratedConversion('medium'))->toBeTrue()
            ->and($response->json('data.image.thumb'))->toEndWith('shirts-thumb.webp')
            ->and($response->json('data.image.medium'))->toEndWith('shirts-medium.webp')
            ->and($response->json('data.image.original'))->toEndWith('shirts.png')
            ->and(Activity::query()->where('event', 'category.updated')->pluck('properties')->map->all()->all())
            ->toBe([['fields' => ['image']], ['fields' => ['image']]]);

        actingAs($admin)
            ->getJson(route('api.v1.admin.categories.index'))
            ->assertJsonPath('data.0.image.thumb', $response->json('data.image.thumb'));
    });

    it('rejects files that are not jpg, png or webp images', function (UploadedFile $file) {
        actingAs(categoryManager())
            ->postJson(route('api.v1.admin.categories.image.store', ['category' => Category::factory()->create()]), ['image' => $file])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['image']);
    })->with([
        'pdf' => fn () => UploadedFile::fake()->create('menu.pdf', 100, 'application/pdf'),
        'gif' => fn () => UploadedFile::fake()->image('anim.gif'),
        'too large' => fn () => UploadedFile::fake()->image('huge.jpg')->size(6000),
    ]);

    it('removes the image', function () {
        $category = Category::factory()->create();
        $category->addMedia(UploadedFile::fake()->image('shirts.jpg'))->toMediaCollection(Category::IMAGE_COLLECTION);

        actingAs(categoryManager())
            ->deleteJson(route('api.v1.admin.categories.image.destroy', ['category' => $category]))
            ->assertOk()
            ->assertJsonPath('data.image', null);

        expect($category->refresh()->getMedia(Category::IMAGE_COLLECTION))->toHaveCount(0)
            ->and(categoryEvent('category.updated')->properties->all())->toBe(['fields' => ['image']]);
    });
});

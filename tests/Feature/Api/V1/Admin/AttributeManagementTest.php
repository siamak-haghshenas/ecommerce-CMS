<?php

declare(strict_types=1);

use App\Enums\AttributeType;
use App\Enums\UserRole;
use App\Models\Attribute;
use App\Models\Category;
use App\Models\Option;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\withHeader;

function catalogManager(UserRole $role = UserRole::Admin): User
{
    return User::factory()->withRole($role)->create();
}

/**
 * @return array<string, mixed>
 */
function attributePayload(array $overrides = []): array
{
    return [
        'name' => 'جنس',
        'slug' => 'material',
        'type' => 'select',
        'unit' => null,
        'is_filterable' => true,
        'values' => [
            ['value' => 'نخی', 'slug' => 'cotton'],
            ['value' => 'کتان', 'slug' => 'linen'],
        ],
        ...$overrides,
    ];
}

/**
 * @return array<string, mixed>
 */
function optionPayload(array $overrides = []): array
{
    return [
        'name' => 'رنگ',
        'slug' => 'color',
        'display_type' => 'color',
        'values' => [
            ['value' => 'قرمز', 'slug' => 'red', 'color_hex' => '#E11D48'],
            ['value' => 'آبی', 'slug' => 'blue', 'color_hex' => '#2563eb'],
        ],
        ...$overrides,
    ];
}

describe('access', function () {
    it('returns 401 to guests', function () {
        getJson(route('api.v1.admin.attributes.index'))->assertUnauthorized();
    });

    it('returns 403 to roles without the attribute permissions', function (UserRole $role, string $method, string $routeName) {
        actingAs(catalogManager($role))->json($method, route($routeName), attributePayload())->assertForbidden();
    })->with([
        'author listing attributes' => [UserRole::Author, 'GET', 'api.v1.admin.attributes.index'],
        'author creating an attribute' => [UserRole::Author, 'POST', 'api.v1.admin.attributes.store'],
        'author listing options' => [UserRole::Author, 'GET', 'api.v1.admin.options.index'],
        'customer listing attributes' => [UserRole::Customer, 'GET', 'api.v1.admin.attributes.index'],
    ]);
});

describe('attributes', function () {
    it('creates a select attribute with ordered values, last in the list, and logs it', function () {
        Attribute::factory()->create(['position' => 3]);
        $admin = catalogManager();

        actingAs($admin)
            ->postJson(route('api.v1.admin.attributes.store'), attributePayload(['slug' => ' Material ']))
            ->assertCreated()
            ->assertJsonPath('data.slug', 'material')
            ->assertJsonPath('data.type', 'select')
            ->assertJsonPath('data.is_filterable', true)
            ->assertJsonPath('data.position', 4)
            ->assertJsonPath('data.values.*.slug', ['cotton', 'linen'])
            ->assertJsonPath('data.values.0.value', 'نخی');

        expect(Activity::query()->where('event', 'attribute.created')->sole()->properties->all())->toBe(['name' => 'جنس']);
    });

    it('keeps a unit only for number attributes and no values for non-select types', function () {
        actingAs(catalogManager())
            ->postJson(route('api.v1.admin.attributes.store'), attributePayload([
                'name' => 'Screen size', 'slug' => 'screen-size', 'type' => 'number', 'unit' => 'inch',
            ]))
            ->assertCreated()
            ->assertJsonPath('data.unit', 'inch')
            ->assertJsonPath('data.values', []);

        actingAs(catalogManager())
            ->postJson(route('api.v1.admin.attributes.store'), attributePayload(['slug' => 'waterproof', 'type' => 'boolean', 'unit' => 'cm']))
            ->assertCreated()
            ->assertJsonPath('data.unit', null);
    });

    it('syncs values on update: keeps ids, renames, reorders, swaps slugs, adds and removes', function () {
        $attribute = Attribute::factory()->select(['Cotton' => 'cotton', 'Linen' => 'linen', 'Wool' => 'wool'])->create(['slug' => 'material']);
        [$cotton, $linen, $wool] = $attribute->values->all();

        actingAs(catalogManager())
            ->putJson(route('api.v1.admin.attributes.update', ['attribute' => $attribute]), attributePayload(['values' => [
                ['id' => $linen->id, 'value' => 'Linen', 'slug' => 'cotton'],
                ['id' => $cotton->id, 'value' => 'Pure cotton', 'slug' => 'linen'],
                ['value' => 'Silk', 'slug' => 'silk'],
            ]]))
            ->assertOk()
            ->assertJsonPath('data.values.*.id', [$linen->id, $cotton->id, $attribute->values()->where('slug', 'silk')->value('id')])
            ->assertJsonPath('data.values.*.slug', ['cotton', 'linen', 'silk'])
            ->assertJsonPath('data.values.1.value', 'Pure cotton');

        expect($wool->fresh())->toBeNull()
            ->and(Activity::query()->where('event', 'attribute.updated')->sole()->properties['fields'])->toContain('values');
    });

    it('does not log anything when nothing changed', function () {
        $attribute = Attribute::factory()->select(['نخی' => 'cotton', 'کتان' => 'linen'])->create([
            'name' => 'جنس', 'slug' => 'material', 'is_filterable' => true,
        ]);
        [$cotton, $linen] = $attribute->values->all();

        actingAs(catalogManager())
            ->putJson(route('api.v1.admin.attributes.update', ['attribute' => $attribute]), attributePayload(['values' => [
                ['id' => $cotton->id, 'value' => 'نخی', 'slug' => 'cotton'],
                ['id' => $linen->id, 'value' => 'کتان', 'slug' => 'linen'],
            ]]))
            ->assertOk();

        expect(Activity::query()->count())->toBe(0);
    });

    it('deletes the values when the type changes away from select', function () {
        $attribute = Attribute::factory()->select()->create(['slug' => 'material']);

        actingAs(catalogManager())
            ->putJson(route('api.v1.admin.attributes.update', ['attribute' => $attribute]), attributePayload(['type' => 'text']))
            ->assertOk()
            ->assertJsonPath('data.values', []);

        expect($attribute->values()->count())->toBe(0);
    });

    it('validates the attribute', function (array $overrides, string $field) {
        Attribute::factory()->create(['slug' => 'taken']);

        actingAs(catalogManager())
            ->postJson(route('api.v1.admin.attributes.store'), attributePayload($overrides))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field]);
    })->with([
        'taken slug' => [['slug' => 'taken'], 'slug'],
        'unknown type' => [['type' => 'date'], 'type'],
        'select without values' => [['values' => []], 'values'],
        'value without slug' => [['values' => [['value' => 'نخی', 'slug' => '']]], 'values.0.slug'],
        'persian value slug' => [['values' => [['value' => 'نخی', 'slug' => 'نخی']]], 'values.0.slug'],
        'duplicate value slugs' => [['values' => [['value' => 'a', 'slug' => 'a'], ['value' => 'b', 'slug' => 'a']]], 'values.1.slug'],
    ]);

    it('refuses a value id that belongs to another attribute', function () {
        $attribute = Attribute::factory()->select()->create();
        $other = Attribute::factory()->select()->create();

        actingAs(catalogManager())
            ->putJson(route('api.v1.admin.attributes.update', ['attribute' => $attribute]), attributePayload([
                'slug' => $attribute->slug,
                'values' => [['id' => $other->values[0]->id, 'value' => 'x', 'slug' => 'x']],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['values.0.id']);
    });

    it('lists attributes in order with values and category counts', function () {
        $second = Attribute::factory()->create(['position' => 1]);
        $first = Attribute::factory()->select()->create(['position' => 0]);
        Category::factory()->create()->attributeDefinitions()->attach($first);

        actingAs(catalogManager())
            ->getJson(route('api.v1.admin.attributes.index'))
            ->assertOk()
            ->assertJsonPath('data.*.id', [$first->id, $second->id])
            ->assertJsonPath('data.0.categories_count', 1)
            ->assertJsonCount(2, 'data.0.values');
    });

    it('reorders and deletes attributes', function () {
        $a = Attribute::factory()->create(['position' => 0]);
        $b = Attribute::factory()->create(['position' => 1]);
        $admin = catalogManager();

        actingAs($admin)
            ->putJson(route('api.v1.admin.attributes.order'), ['ids' => [$b->id, $a->id]])
            ->assertOk()
            ->assertJsonPath('data.*.id', [$b->id, $a->id]);

        withHeader('Accept-Language', 'en')
            ->actingAs($admin)
            ->putJson(route('api.v1.admin.attributes.order'), ['ids' => [$a->id]])
            ->assertConflict()
            ->assertJsonPath('code', 'catalog_list_changed');

        actingAs($admin)->deleteJson(route('api.v1.admin.attributes.destroy', ['attribute' => $a]))->assertNoContent();

        expect(Attribute::query()->pluck('id')->all())->toBe([$b->id])
            ->and(Activity::query()->where('event', 'attribute.deleted')->sole()->properties['name'])->toBe($a->name);
    });
});

describe('options', function () {
    it('creates a color option with lowercase colors', function () {
        actingAs(catalogManager())
            ->postJson(route('api.v1.admin.options.store'), optionPayload())
            ->assertCreated()
            ->assertJsonPath('data.display_type', 'color')
            ->assertJsonPath('data.values.*.color_hex', ['#e11d48', '#2563eb']);
    });

    it('drops colors for text options', function () {
        actingAs(catalogManager())
            ->postJson(route('api.v1.admin.options.store'), optionPayload(['slug' => 'size', 'display_type' => 'text']))
            ->assertCreated()
            ->assertJsonPath('data.values.*.color_hex', [null, null]);
    });

    it('validates the option', function (array $overrides, string $field) {
        actingAs(catalogManager())
            ->postJson(route('api.v1.admin.options.store'), optionPayload($overrides))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field]);
    })->with([
        'no values' => [['values' => []], 'values'],
        'color option value without color' => [['values' => [['value' => 'قرمز', 'slug' => 'red', 'color_hex' => null]]], 'values.0.color_hex'],
        'invalid color' => [['values' => [['value' => 'قرمز', 'slug' => 'red', 'color_hex' => 'red']]], 'values.0.color_hex'],
        'unknown display type' => [['display_type' => 'image'], 'display_type'],
    ]);

    it('updates, reorders and deletes options', function () {
        $size = Option::factory()->withValues()->create(['slug' => 'size', 'position' => 0]);
        $color = Option::factory()->create(['position' => 1]);
        $admin = catalogManager();

        actingAs($admin)
            ->putJson(route('api.v1.admin.options.update', ['option' => $size]), optionPayload([
                'name' => 'سایز',
                'slug' => 'size',
                'display_type' => 'text',
                'values' => [['id' => $size->values[2]->id, 'value' => 'L', 'slug' => 'l'], ['value' => 'XL', 'slug' => 'xl']],
            ]))
            ->assertOk()
            ->assertJsonPath('data.values.*.slug', ['l', 'xl']);

        actingAs($admin)
            ->putJson(route('api.v1.admin.options.order'), ['ids' => [$color->id, $size->id]])
            ->assertOk()
            ->assertJsonPath('data.*.id', [$color->id, $size->id]);

        actingAs($admin)->deleteJson(route('api.v1.admin.options.destroy', ['option' => $color]))->assertNoContent();

        expect(Option::query()->count())->toBe(1);
    });
});

describe('category attributes', function () {
    it('sets a category’s own attributes in order and shows inherited ones from the nearest ancestor', function () {
        [$material, $size, $color, $weight] = Attribute::factory()->count(4)->create()->all();
        $clothing = Category::factory()->create(['name' => 'Clothing']);
        $men = Category::factory()->childOf($clothing)->create(['name' => 'Men']);
        $shirts = Category::factory()->childOf($men)->create();
        $clothing->attributeDefinitions()->attach([$material->id => ['position' => 0], $size->id => ['position' => 1]]);
        $men->attributeDefinitions()->attach([$size->id => ['position' => 0]]);
        $admin = catalogManager();

        actingAs($admin)
            ->putJson(route('api.v1.admin.categories.attributes.update', ['category' => $shirts]), ['ids' => [$weight->id, $color->id]])
            ->assertOk()
            ->assertJsonPath('data.own.*.id', [$weight->id, $color->id])
            ->assertJsonPath('data.inherited.*.attribute.id', [$size->id, $material->id])
            ->assertJsonPath('data.inherited.0.from.name', 'Men')
            ->assertJsonPath('data.inherited.1.from.name', 'Clothing');

        expect(Activity::query()->where('event', 'category.attributes_changed')->sole()->properties['attribute_ids'])
            ->toBe([$weight->id, $color->id]);

        actingAs($admin)
            ->putJson(route('api.v1.admin.categories.attributes.update', ['category' => $shirts]), ['ids' => []])
            ->assertOk()
            ->assertJsonPath('data.own', []);
    });

    it('needs categories.manage to change and refuses unknown attributes', function () {
        $category = Category::factory()->create();

        actingAs(catalogManager(UserRole::Author))
            ->putJson(route('api.v1.admin.categories.attributes.update', ['category' => $category]), ['ids' => []])
            ->assertForbidden();

        actingAs(catalogManager())
            ->putJson(route('api.v1.admin.categories.attributes.update', ['category' => $category]), ['ids' => [999]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ids.0']);
    });

    it('keeps attribute types as enum values', function () {
        expect(Attribute::factory()->create(['type' => 'number'])->type)->toBe(AttributeType::Number);
    });
});

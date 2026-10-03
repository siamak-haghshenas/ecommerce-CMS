<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\AiRequest;
use App\Models\Brand;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\getJson;
use function Pest\Laravel\withHeader;

function brandManager(UserRole $role = UserRole::Admin): User
{
    return User::factory()->withRole($role)->create();
}

/**
 * @return array<string, mixed>
 */
function brandPayload(array $overrides = []): array
{
    return [
        'name' => 'آدیداس',
        'slug' => 'adidas',
        'description' => null,
        'website' => 'https://www.adidas.com',
        'meta_title' => null,
        'meta_description' => null,
        'focus_keyphrase' => null,
        'is_active' => true,
        ...$overrides,
    ];
}

function brandEvent(string $event): Activity
{
    return Activity::query()->where('event', $event)->sole();
}

describe('access', function () {
    it('returns 401 to guests', function () {
        getJson(route('api.v1.admin.brands.index'))->assertUnauthorized();
    });

    it('returns 403 to roles without the brand permissions', function (UserRole $role, string $method, string $routeName) {
        $brand = Brand::factory()->create();

        actingAs(brandManager($role))
            ->json($method, route($routeName, ['brand' => $brand]), brandPayload())
            ->assertForbidden();
    })->with([
        'author listing' => [UserRole::Author, 'GET', 'api.v1.admin.brands.index'],
        'author creating' => [UserRole::Author, 'POST', 'api.v1.admin.brands.store'],
        'author deleting' => [UserRole::Author, 'DELETE', 'api.v1.admin.brands.destroy'],
        'customer listing' => [UserRole::Customer, 'GET', 'api.v1.admin.brands.index'],
    ]);

    it('lets admins and super admins manage brands', function (UserRole $role) {
        actingAs(brandManager($role))->postJson(route('api.v1.admin.brands.store'), brandPayload())->assertCreated();
    })->with([UserRole::Admin, UserRole::SuperAdmin]);
});

describe('list', function () {
    it('returns every brand in manual order, hidden ones included, without pagination', function () {
        $second = Brand::factory()->create(['position' => 1]);
        $hidden = Brand::factory()->hidden()->create(['position' => 2]);
        $first = Brand::factory()->create(['position' => 0]);

        actingAs(brandManager())
            ->getJson(route('api.v1.admin.brands.index'))
            ->assertOk()
            ->assertJsonPath('data.*.id', [$first->id, $second->id, $hidden->id])
            ->assertJsonPath('data.2.is_active', false)
            ->assertJsonMissingPath('meta');
    });
});

describe('create and update', function () {
    it('creates a brand last in the list, with its SEO score, and logs it', function () {
        Brand::factory()->create(['position' => 4]);
        $admin = brandManager();

        actingAs($admin)
            ->postJson(route('api.v1.admin.brands.store'), brandPayload(['slug' => ' Adidas ', 'focus_keyphrase' => 'آدیداس']))
            ->assertCreated()
            ->assertJsonPath('data.name', 'آدیداس')
            ->assertJsonPath('data.slug', 'adidas')
            ->assertJsonPath('data.website', 'https://www.adidas.com')
            ->assertJsonPath('data.position', 5)
            ->assertJsonPath('data.focus_keyphrase', 'آدیداس')
            // Good: keyphrase set, in title (the name), in slug («آدیداس» → adidas), unique. Ok: no SEO
            // title, no logo. Bad: keyphrase missing from meta description and description, both empty.
            ->assertJsonPath('data.seo_score', 50);

        expect(brandEvent('brand.created')->causer_id)->toBe($admin->id);
    });

    it('validates the fields', function (array $overrides, string $field) {
        Brand::factory()->create(['name' => 'Nike', 'persian_name' => 'نایکی', 'slug' => 'nike']);

        actingAs(brandManager())
            ->postJson(route('api.v1.admin.brands.store'), brandPayload($overrides))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field]);
    })->with([
        'missing name' => [['name' => ''], 'name'],
        'taken name' => [['name' => 'Nike'], 'name'],
        'taken slug' => [['slug' => 'nike'], 'slug'],
        'persian slug' => [['slug' => 'آدیداس'], 'slug'],
        'website without http' => [['website' => 'adidas.com'], 'website'],
        'website with another scheme' => [['website' => 'javascript:alert(1)'], 'website'],
        'missing visibility' => [['is_active' => null], 'is_active'],
        'taken persian name' => [['persian_name' => 'نایکی'], 'persian_name'],
    ]);

    it('updates a brand, keeping its own name and slug, and logs the changed field names', function () {
        $brand = Brand::factory()->create(brandPayload());

        actingAs(brandManager())
            ->putJson(route('api.v1.admin.brands.update', ['brand' => $brand]), brandPayload(['website' => null, 'is_active' => false]))
            ->assertOk()
            ->assertJsonPath('data.website', null)
            ->assertJsonPath('data.is_active', false);

        expect(brandEvent('brand.updated')->properties->all())->toBe(['fields' => ['website', 'is_active']]);
    });

    it('does not log anything when nothing changed', function () {
        $brand = Brand::factory()->create(brandPayload());

        actingAs(brandManager())
            ->putJson(route('api.v1.admin.brands.update', ['brand' => $brand]), brandPayload())
            ->assertOk();

        expect(Activity::query()->count())->toBe(0);
    });

    it('flags the same keyphrase on two brands and clears it when one is deleted', function () {
        $admin = brandManager();
        $first = actingAs($admin)->postJson(route('api.v1.admin.brands.store'), brandPayload(['focus_keyphrase' => 'کفش ورزشی']))->json('data');
        $second = actingAs($admin)
            ->postJson(route('api.v1.admin.brands.store'), brandPayload(['name' => 'Nike', 'slug' => 'nike', 'focus_keyphrase' => 'كفش ورزشي']))
            ->json('data');

        expect(Brand::query()->find($first['id'])?->seo_score)->toBeLessThan($first['seo_score']);

        actingAs($admin)->deleteJson(route('api.v1.admin.brands.destroy', ['brand' => $second['id']]))->assertNoContent();

        expect(Brand::query()->find($first['id'])?->seo_score)->toBe($first['seo_score']);
    });
});

it('stores the optional Persian name next to the brand name', function () {
    $admin = brandManager();

    $brand = actingAs($admin)
        ->postJson(route('api.v1.admin.brands.store'), brandPayload(['name' => 'Adidas', 'persian_name' => ' آدیداس ']))
        ->assertCreated()
        ->assertJsonPath('data.name', 'Adidas')
        ->assertJsonPath('data.persian_name', 'آدیداس')
        ->json('data');

    actingAs($admin)
        ->putJson(route('api.v1.admin.brands.update', ['brand' => $brand['id']]), brandPayload(['name' => 'Adidas', 'persian_name' => '']))
        ->assertOk()
        ->assertJsonPath('data.persian_name', null);
});

describe('delete', function () {
    it('deletes a brand and keeps its name in the log', function () {
        $brand = Brand::factory()->create(['name' => 'Puma', 'slug' => 'puma']);

        actingAs(brandManager())->deleteJson(route('api.v1.admin.brands.destroy', ['brand' => $brand]))->assertNoContent();

        expect(Brand::query()->count())->toBe(0)
            ->and(brandEvent('brand.deleted')->properties->all())->toBe(['name' => 'Puma', 'slug' => 'puma']);
    });
});

describe('order', function () {
    it('saves the new order and logs it once', function () {
        [$a, $b, $c] = [
            Brand::factory()->create(['position' => 0]),
            Brand::factory()->create(['position' => 1]),
            Brand::factory()->create(['position' => 2]),
        ];

        actingAs(brandManager())
            ->putJson(route('api.v1.admin.brands.order'), ['ids' => [$c->id, $a->id, $b->id]])
            ->assertOk()
            ->assertJsonPath('data.*.id', [$c->id, $a->id, $b->id]);

        expect(Activity::query()->where('event', 'brand.reordered')->count())->toBe(1);
    });

    it('returns 409 when the ids are not exactly the current brands', function (string $case) {
        $a = Brand::factory()->create(['position' => 0]);
        $b = Brand::factory()->create(['position' => 1]);
        $ids = $case === 'missing' ? [$b->id] : [$b->id, $a->id, Brand::factory()->create()->id];

        if ($case === 'added meanwhile') {
            $ids = [$b->id, $a->id];
            Brand::factory()->create();
        }

        withHeader('Accept-Language', 'en')
            ->actingAs(brandManager())
            ->putJson(route('api.v1.admin.brands.order'), ['ids' => $ids])
            ->assertConflict()
            ->assertJsonPath('code', 'brand_list_changed');

        expect($a->refresh()->position)->toBe(0);
    })->with(['missing', 'added meanwhile']);

    it('validates the order payload', function () {
        actingAs(brandManager())
            ->putJson(route('api.v1.admin.brands.order'), ['ids' => [999, 999]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ids.0', 'ids.1']);
    });
});

describe('logo', function () {
    beforeEach(fn () => Storage::fake('public'));

    it('uploads a logo with fitted conversions, counts it in the score and replaces the previous one', function () {
        $admin = brandManager();
        $brand = actingAs($admin)->postJson(route('api.v1.admin.brands.store'), brandPayload())->json('data');

        actingAs($admin)
            ->postJson(route('api.v1.admin.brands.logo.store', ['brand' => $brand['id']]), ['logo' => UploadedFile::fake()->image('old.png', 300, 100)])
            ->assertOk();

        $response = actingAs($admin)
            ->postJson(route('api.v1.admin.brands.logo.store', ['brand' => $brand['id']]), ['logo' => UploadedFile::fake()->image('adidas.png', 800, 300)])
            ->assertOk()
            // Without a keyphrase there are 5 checks, so the logo (ok → good) adds 10 points.
            ->assertJsonPath('data.seo_score', $brand['seo_score'] + 10);

        $media = Brand::query()->findOrFail($brand['id'])->getMedia(Brand::LOGO_COLLECTION);

        expect($media)->toHaveCount(1)
            ->and($media->first()?->hasGeneratedConversion('thumb'))->toBeTrue()
            ->and($response->json('data.logo.thumb'))->toEndWith('adidas-thumb.webp')
            ->and(Activity::query()->where('event', 'brand.updated')->get()->map(fn (Activity $entry): array => $entry->properties->all())->all())
            ->toBe([['fields' => ['logo']], ['fields' => ['logo']]]);
    });

    it('rejects SVG and other files', function (UploadedFile $file) {
        actingAs(brandManager())
            ->postJson(route('api.v1.admin.brands.logo.store', ['brand' => Brand::factory()->create()]), ['logo' => $file])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['logo']);
    })->with([
        'svg' => fn () => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
        'pdf' => fn () => UploadedFile::fake()->create('logo.pdf', 100, 'application/pdf'),
        'too large' => fn () => UploadedFile::fake()->image('logo.png')->size(6000),
    ]);

    it('removes the logo', function () {
        $brand = Brand::factory()->create();
        $brand->addMedia(UploadedFile::fake()->image('logo.png'))->toMediaCollection(Brand::LOGO_COLLECTION);

        actingAs(brandManager())
            ->deleteJson(route('api.v1.admin.brands.logo.destroy', ['brand' => $brand]))
            ->assertOk()
            ->assertJsonPath('data.logo', null);

        expect($brand->refresh()->getMedia(Brand::LOGO_COLLECTION))->toHaveCount(0);
    });
});

describe('SEO suggestions and scores', function () {
    it('asks for Persian suggestions when only the Persian name is Persian', function () {
        ai()->respondWith(['keyphrases' => ['آدیداس'], 'titles' => ['آدیداس'], 'meta_descriptions' => ['آدیداس']]);

        actingAs(brandManager())
            ->postJson(route('api.v1.admin.seo-suggestions'), ['type' => 'brand', 'name' => 'Adidas', 'alternate_name' => 'آدیداس'])
            ->assertOk();

        expect(ai()->prompts()[0]->system)->toContain('Persian (Farsi)')
            ->and(ai()->prompts()[0]->message)->toContain('Also written as: آدیداس');
    });

    it('gives brand managers AI suggestions for a brand page', function () {
        ai()->respondWith(['keyphrases' => ['آدیداس'], 'titles' => ['خرید محصولات آدیداس'], 'meta_descriptions' => ['توضیح']]);
        $brand = Brand::factory()->create();

        actingAs(brandManager())
            ->postJson(route('api.v1.admin.seo-suggestions'), ['type' => 'brand', 'name' => 'آدیداس', 'subject_id' => $brand->id])
            ->assertOk()
            ->assertJsonPath('data.keyphrases', ['آدیداس']);

        expect(ai()->prompts()[0]->system)->toContain('a brand page')
            ->and(AiRequest::query()->sole()->only(['feature', 'subject_id']))->toBe(['feature' => 'brand_seo', 'subject_id' => $brand->id]);
    });

    it('refuses suggestions for a brand that does not exist or to staff without the permission', function () {
        actingAs(brandManager())
            ->postJson(route('api.v1.admin.seo-suggestions'), ['type' => 'brand', 'name' => 'Nike', 'subject_id' => 999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['subject_id']);

        actingAs(brandManager(UserRole::Author))
            ->postJson(route('api.v1.admin.seo-suggestions'), ['type' => 'brand', 'name' => 'Nike'])
            ->assertForbidden();

        expect(ai()->prompts())->toBe([]);
    });

    it('re-scores brands from the command', function () {
        $brand = Brand::factory()->create(['seo_score' => null]);

        artisan('seo:score', ['--type' => 'brand'])->assertSuccessful();

        expect($brand->refresh()->seo_score)->not->toBeNull();
    });
});

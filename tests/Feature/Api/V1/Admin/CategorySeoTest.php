<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Exceptions\AiException;
use App\Models\AiRequest;
use App\Models\Category;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\withHeader;

function seoManager(UserRole $role = UserRole::Admin): User
{
    return User::factory()->withRole($role)->create();
}

/**
 * @return array<string, mixed>
 */
function seoCategoryPayload(array $overrides = []): array
{
    return [
        'name' => 'پیراهن مردانه',
        'slug' => 'pirahn-mrdane',
        'parent_id' => null,
        'description' => null,
        'meta_title' => 'پیراهن مردانه | خرید انواع پیراهن رسمی و اسپرت',
        'meta_description' => null,
        'focus_keyphrase' => 'پیراهن مردانه',
        'is_active' => true,
        ...$overrides,
    ];
}

/**
 * @return array{keyphrases: list<string>, titles: list<string>, meta_descriptions: list<string>}
 */
function aiSuggestions(): array
{
    return [
        'keyphrases' => ['پیراهن مردانه', 'خرید پیراهن مردانه', 'پیراهن مردانه رسمی'],
        'titles' => ['پیراهن مردانه | مدل‌های رسمی و اسپرت', 'خرید پیراهن مردانه با کیفیت', '«پیراهن مردانه نخی و لینن»'],
        'meta_descriptions' => ['توضیح اول', 'توضیح دوم', 'توضیح سوم'],
    ];
}

describe('stored scores', function () {
    it('stores the SEO score and focus keyphrase when a category is created', function () {
        actingAs(seoManager())
            ->postJson(route('api.v1.admin.categories.store'), seoCategoryPayload())
            ->assertCreated()
            ->assertJsonPath('data.focus_keyphrase', 'پیراهن مردانه')
            // Good: keyphrase set, in title, in slug, unique, title length. Bad: meta description
            // (missing keyphrase and empty), description (missing keyphrase and empty). Ok: no image.
            ->assertJsonPath('data.seo_score', 55)
            ->assertJsonPath('data.readability_score', null);
    });

    it('updates the score when the category changes', function () {
        $admin = seoManager();
        $id = actingAs($admin)->postJson(route('api.v1.admin.categories.store'), seoCategoryPayload())->json('data.id');

        actingAs($admin)
            ->putJson(route('api.v1.admin.categories.update', ['category' => $id]), seoCategoryPayload([
                'meta_description' => 'خرید پیراهن مردانه رسمی، اسپرت و روزمره از پارچه‌های نخی و لینن با کیفیت. راهنمای سایز، ارسال سریع به سراسر کشور و مرجوعی هفت روزه برای همه سفارش‌ها.',
            ]))
            ->assertOk()
            ->assertJsonPath('data.seo_score', 75);
    });

    it('lowers the score of both categories that target the same keyphrase, and restores it on delete', function () {
        $admin = seoManager();
        $first = actingAs($admin)->postJson(route('api.v1.admin.categories.store'), seoCategoryPayload())->json('data');

        // Typed on an Arabic keyboard with a half-space: still the same keyphrase.
        $second = actingAs($admin)
            ->postJson(route('api.v1.admin.categories.store'), seoCategoryPayload([
                'name' => 'پیراهن',
                'slug' => 'shirts',
                'focus_keyphrase' => 'پيراهن‌ مردانه',
            ]))
            ->json('data');

        expect(Category::query()->find($first['id'])?->seo_score)->toBeLessThan($first['seo_score']);

        actingAs($admin)->deleteJson(route('api.v1.admin.categories.destroy', ['category' => $second['id']]))->assertNoContent();

        expect(Category::query()->find($first['id'])?->seo_score)->toBe($first['seo_score']);
    });

    it('counts the image in the score', function () {
        Storage::fake('public');
        $admin = seoManager();
        $category = actingAs($admin)->postJson(route('api.v1.admin.categories.store'), seoCategoryPayload())->json('data');

        actingAs($admin)
            ->putJson(route('api.v1.admin.categories.update', ['category' => $category['id']]), seoCategoryPayload(['slug' => $category['slug'], 'image_id' => libraryImage('shirts.jpg')->id]))
            ->assertOk()
            ->assertJsonPath('data.seo_score', $category['seo_score'] + 5);
    });

    it('shows the keyphrase and scores in the admin tree but never in the storefront API', function () {
        $admin = seoManager();
        actingAs($admin)->postJson(route('api.v1.admin.categories.store'), seoCategoryPayload())->assertCreated();

        actingAs($admin)
            ->getJson(route('api.v1.admin.categories.index'))
            ->assertJsonPath('data.0.focus_keyphrase', 'پیراهن مردانه')
            ->assertJsonPath('data.0.seo_score', 55);

        $storefront = $this->getJson(route('api.v1.categories.show', ['slug' => 'pirahn-mrdane']))->assertOk()->json('data');

        expect($storefront)->not->toHaveKeys(['focus_keyphrase', 'seo_score', 'readability_score']);
    });

    it('re-scores every category from the command', function () {
        $category = Category::factory()->create(['focus_keyphrase' => null, 'seo_score' => null]);

        artisan('seo:score')->assertSuccessful();

        expect($category->refresh()->seo_score)->toBe(20);
    });
});

describe('AI suggestions', function () {
    it('returns three suggestions per field without saving anything and records the call', function () {
        ai()->respondWith(aiSuggestions());
        $admin = seoManager();
        $parent = Category::factory()->create(['name' => 'پوشاک']);
        $category = Category::factory()->childOf($parent)->create(['meta_title' => null]);

        actingAs($admin)
            ->postJson(route('api.v1.admin.seo-suggestions'), ['type' => 'category',
                'name' => 'پیراهن مردانه',
                'description' => 'پیراهن‌های نخی و لینن',
                'parent_id' => $parent->id,
                'focus_keyphrase' => 'پیراهن مردانه',
                'subject_id' => $category->id,
            ])
            ->assertOk()
            ->assertExactJson(['data' => [
                'keyphrases' => ['پیراهن مردانه', 'خرید پیراهن مردانه', 'پیراهن مردانه رسمی'],
                // Quotation marks around an option are stripped.
                'titles' => ['پیراهن مردانه | مدل‌های رسمی و اسپرت', 'خرید پیراهن مردانه با کیفیت', 'پیراهن مردانه نخی و لینن'],
                'meta_descriptions' => ['توضیح اول', 'توضیح دوم', 'توضیح سوم'],
            ]]);

        $prompt = ai()->prompts()[0];

        expect($prompt->system)->toContain('Persian (Farsi)')
            ->and($prompt->message)->toContain('Parent categories (top first): پوشاک')
            ->and($prompt->message)->toContain("Admin's focus keyphrase: پیراهن مردانه")
            ->and($category->refresh()->meta_title)->toBeNull();

        $record = AiRequest::query()->sole();

        expect($record->only(['user_id', 'feature', 'subject_id', 'driver', 'model', 'input_tokens', 'output_tokens', 'status', 'error_code']))->toBe([
            'user_id' => $admin->id,
            'feature' => 'category_seo',
            'subject_id' => $category->id,
            'driver' => 'fake',
            'model' => 'fake-model',
            'input_tokens' => 120,
            'output_tokens' => 80,
            'status' => 'succeeded',
            'error_code' => null,
        ]);
    });

    it('asks for English text when the name is English', function () {
        ai()->respondWith(aiSuggestions());

        actingAs(seoManager())->postJson(route('api.v1.admin.seo-suggestions'), ['type' => 'category', 'name' => 'Men shirts'])->assertOk();

        expect(ai()->prompts()[0]->system)->toContain('Write everything in English.');
    });

    it('is limited to category managers', function (UserRole $role) {
        actingAs(seoManager($role))
            ->postJson(route('api.v1.admin.seo-suggestions'), ['type' => 'category', 'name' => 'Shirts'])
            ->assertForbidden();

        expect(ai()->prompts())->toBe([]);
    })->with([UserRole::Author, UserRole::Customer]);

    it('requires a name', function () {
        actingAs(seoManager())
            ->postJson(route('api.v1.admin.seo-suggestions'), ['type' => 'category', 'parent_id' => 999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'parent_id']);
    });

    it('reports AI failures with a code and records them', function (AiException $failure, int $status, string $code) {
        ai()->failWith($failure);

        withHeader('Accept-Language', 'en')
            ->actingAs(seoManager())
            ->postJson(route('api.v1.admin.seo-suggestions'), ['type' => 'category', 'name' => 'Shirts'])
            ->assertStatus($status)
            ->assertJsonPath('code', $code);

        expect(AiRequest::query()->sole()->only(['status', 'error_code']))->toBe(['status' => 'failed', 'error_code' => $code]);
    })->with([
        'not configured' => [AiException::notConfigured(), 503, 'ai_not_configured'],
        'unavailable' => [AiException::unavailable(), 503, 'ai_unavailable'],
    ]);

    it('rejects an answer without usable options', function () {
        ai()->respondWith(['keyphrases' => ['  '], 'titles' => [], 'meta_descriptions' => 'not a list']);

        actingAs(seoManager())
            ->postJson(route('api.v1.admin.seo-suggestions'), ['type' => 'category', 'name' => 'Shirts'])
            ->assertStatus(502)
            ->assertJsonPath('code', 'ai_invalid_response');
    });

    it('limits each staff member per hour', function () {
        config(['ai.limits.per_user_per_hour' => 2]);
        ai()->respondWith(aiSuggestions());
        $admin = seoManager();

        foreach (range(1, 2) as $attempt) {
            actingAs($admin)->postJson(route('api.v1.admin.seo-suggestions'), ['type' => 'category', 'name' => 'Shirts'])->assertOk();
        }

        actingAs($admin)
            ->postJson(route('api.v1.admin.seo-suggestions'), ['type' => 'category', 'name' => 'Shirts'])
            ->assertTooManyRequests()
            ->assertJsonPath('code', 'too_many_requests');

        actingAs(seoManager())->postJson(route('api.v1.admin.seo-suggestions'), ['type' => 'category', 'name' => 'Shirts'])->assertOk();
    });

    it('limits the whole store per day', function () {
        config(['ai.limits.per_day' => 2]);
        ai()->respondWith(aiSuggestions());

        foreach (range(1, 2) as $attempt) {
            actingAs(seoManager())->postJson(route('api.v1.admin.seo-suggestions'), ['type' => 'category', 'name' => 'Shirts'])->assertOk();
        }

        actingAs(seoManager())
            ->postJson(route('api.v1.admin.seo-suggestions'), ['type' => 'category', 'name' => 'Shirts'])
            ->assertTooManyRequests();
    });
});

describe('Claude driver', function () {
    beforeEach(function () {
        config([
            'ai.default' => 'claude',
            'ai.drivers.claude.api_key' => 'test-key',
            'ai.drivers.claude.model' => 'claude-sonnet-5',
        ]);
    });

    it('asks Claude for a forced tool call and reads the structured answer', function () {
        Http::fake(['api.anthropic.com/*' => Http::response([
            'model' => 'claude-sonnet-5',
            'content' => [['type' => 'tool_use', 'name' => 'seo_suggestions', 'input' => aiSuggestions()]],
            'usage' => ['input_tokens' => 410, 'output_tokens' => 230],
        ])]);

        actingAs(seoManager())
            ->postJson(route('api.v1.admin.seo-suggestions'), ['type' => 'category', 'name' => 'پیراهن مردانه'])
            ->assertOk()
            ->assertJsonPath('data.keyphrases.0', 'پیراهن مردانه');

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.anthropic.com/v1/messages'
            && $request->header('x-api-key') === ['test-key']
            && $request->header('anthropic-version') === ['2023-06-01']
            && $request['model'] === 'claude-sonnet-5'
            && $request['tool_choice'] === ['type' => 'tool', 'name' => 'seo_suggestions']
            && $request['tools'][0]['input_schema']['required'] === ['keyphrases', 'titles', 'meta_descriptions']);

        expect(AiRequest::query()->sole()->only(['driver', 'model', 'input_tokens', 'output_tokens']))->toBe([
            'driver' => 'claude',
            'model' => 'claude-sonnet-5',
            'input_tokens' => 410,
            'output_tokens' => 230,
        ]);
    });

    it('reports a missing key without calling Claude', function () {
        config(['ai.drivers.claude.api_key' => null]);
        Http::fake();

        actingAs(seoManager())
            ->postJson(route('api.v1.admin.seo-suggestions'), ['type' => 'category', 'name' => 'Shirts'])
            ->assertStatus(503)
            ->assertJsonPath('code', 'ai_not_configured');

        Http::assertNothingSent();
    });

    it('maps Claude errors to AI error codes', function (int $status, string $code, int $attempts) {
        Http::fake(['api.anthropic.com/*' => Http::response(['type' => 'error'], $status)]);

        actingAs(seoManager())
            ->postJson(route('api.v1.admin.seo-suggestions'), ['type' => 'category', 'name' => 'Shirts'])
            ->assertJsonPath('code', $code);

        Http::assertSentCount($attempts);
    })->with([
        'rejected key' => [401, 'ai_not_configured', 1],
        'overloaded, retried once' => [529, 'ai_unavailable', 2],
        'bad request' => [400, 'ai_unavailable', 1],
    ]);

    it('rejects an answer without the tool call', function () {
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => 'Sorry']]])]);

        actingAs(seoManager())
            ->postJson(route('api.v1.admin.seo-suggestions'), ['type' => 'category', 'name' => 'Shirts'])
            ->assertStatus(502)
            ->assertJsonPath('code', 'ai_invalid_response');
    });
});

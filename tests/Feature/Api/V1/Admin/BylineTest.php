<?php

declare(strict_types=1);

use App\Enums\MediaUsageRole;
use App\Enums\UserRole;
use App\Models\MediaUsage;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

it('lets staff set and remove their own public byline', function () {
    Storage::fake('public');
    $writer = User::factory()->withRole(UserRole::Author)->create();
    $avatar = libraryImage('me.jpg');

    actingAs($writer)->putJson(route('api.v1.admin.byline.update'), [
        'author_name' => 'سارا',
        'slug' => ' Sara-K ',
        'author_bio' => 'Writes about shoes.',
        'avatar_id' => $avatar->id,
    ])
        ->assertOk()
        ->assertJsonPath('data.slug', 'sara-k')
        ->assertJsonPath('data.avatar.id', $avatar->id);

    expect($writer->fresh()?->hasByline())->toBeTrue()
        ->and(MediaUsage::query()->sole()->role)->toBe(MediaUsageRole::Avatar);

    actingAs($writer)->putJson(route('api.v1.admin.byline.update'), ['author_name' => ''])
        ->assertOk()
        ->assertJsonPath('data.author_name', null)
        ->assertJsonPath('data.slug', null);

    expect(MediaUsage::query()->count())->toBe(0);
});

it('needs a unique slug with a name', function () {
    User::factory()->withRole(UserRole::Admin)->create(['author_name' => 'Sara', 'author_slug' => 'sara']);
    $writer = User::factory()->withRole(UserRole::Author)->create();

    actingAs($writer)->putJson(route('api.v1.admin.byline.update'), ['author_name' => 'Sara', 'slug' => 'sara'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('slug');

    actingAs($writer)->putJson(route('api.v1.admin.byline.update'), ['author_name' => 'Sara'])
        ->assertJsonValidationErrors('slug');
});

it('is not for customers', function () {
    actingAs(User::factory()->withRole(UserRole::Customer)->create())
        ->putJson(route('api.v1.admin.byline.update'), ['author_name' => 'x', 'slug' => 'x'])
        ->assertForbidden();
});

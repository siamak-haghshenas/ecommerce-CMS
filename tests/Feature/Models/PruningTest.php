<?php

declare(strict_types=1);

use App\Models\OtpCode;
use App\Models\User;

use function Pest\Laravel\artisan;
use function Pest\Laravel\travel;

it('removes registrations left unverified for more than 24 hours', function () {
    $abandoned = User::factory()->unverified()->create();
    travel(24)->hours();
    travel(1)->seconds();
    $recent = User::factory()->unverified()->create();
    $verified = User::factory()->create();

    artisan('model:prune', ['--model' => [User::class]])->assertSuccessful();

    expect(User::query()->pluck('id')->all())
        ->toEqualCanonicalizing([$recent->id, $verified->id])
        ->not->toContain($abandoned->id);
});

it('removes codes that expired more than a day ago', function () {
    $old = OtpCode::factory()->create(['expires_at' => now()->minus(days: 1, seconds: 1)]);
    $recent = OtpCode::factory()->expired()->create();

    artisan('model:prune', ['--model' => [OtpCode::class]])->assertSuccessful();

    expect(OtpCode::query()->pluck('id')->all())->toBe([$recent->id])
        ->not->toContain($old->id);
});

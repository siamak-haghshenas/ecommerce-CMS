<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\freezeTime;
use function Pest\Laravel\postJson;
use function Pest\Laravel\travel;
use function Pest\Laravel\withHeader;

function registrationPayload(array $overrides = []): array
{
    return [
        'name' => 'Sara Ahmadi',
        'phone_number' => '09121234567',
        'password' => 'secret-password',
        'password_confirmation' => 'secret-password',
        ...$overrides,
    ];
}

describe('register', function () {
    it('creates an unverified customer and sends a verification code', function () {
        postJson(route('api.v1.auth.register'), registrationPayload(['email' => 'Sara@Example.com']))
            ->assertAccepted()
            ->assertExactJson(['data' => [
                'phone_number' => '09121234567',
                'code_length' => 5,
                'expires_in' => 120,
                'resend_available_in' => 60,
            ]]);

        $user = User::query()->sole();

        expect($user)
            ->name->toBe('Sara Ahmadi')
            ->email->toBe('sara@example.com')
            ->phone_verified_at->toBeNull()
            ->and($user->hasRole(UserRole::Customer))->toBeTrue()
            ->and(sms()->lastCodeFor('09121234567'))->toMatch('/^\d{5}$/');

        assertNoTokenIssued();
    });

    it('normalizes the phone number before storing it', function (string $input) {
        postJson(route('api.v1.auth.register'), registrationPayload(['phone_number' => $input]))
            ->assertAccepted();

        expect(User::query()->sole()->phone_number)->toBe('09121234567');
    })->with([
        'persian digits' => '۰۹۱۲۱۲۳۴۵۶۷',
        'arabic digits' => '٠٩١٢١٢٣٤٥٦٧',
        'plus 98 prefix' => '+989121234567',
        '0098 prefix' => '00989121234567',
        'without leading zero' => '9121234567',
        'with spaces' => '0912 123 4567',
    ]);

    it('returns 422 with messages for every missing field', function () {
        withHeader('Accept-Language', 'en')
            ->postJson(route('api.v1.auth.register'), [])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonValidationErrors([
                'name' => 'The name field is required.',
                'phone_number' => 'The mobile number field is required.',
                'password' => 'The password field is required.',
            ]);
    });

    it('returns 422 for a phone number that is not an Iranian mobile number', function () {
        withHeader('Accept-Language', 'en')
            ->postJson(route('api.v1.auth.register'), registrationPayload(['phone_number' => '02112345678']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone_number' => 'Enter a valid mobile number, such as 09123456789.']);
    });

    it('returns 422 when the password confirmation does not match', function () {
        withHeader('Accept-Language', 'en')
            ->postJson(route('api.v1.auth.register'), registrationPayload(['password_confirmation' => 'different']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password' => 'The password field confirmation does not match.']);
    });

    it('returns 422 when a verified account already uses the phone number', function () {
        User::factory()->create(['phone_number' => '09121234567']);

        withHeader('Accept-Language', 'en')
            ->postJson(route('api.v1.auth.register'), registrationPayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone_number' => 'An account with this mobile number already exists.']);
    });

    it('returns 422 when another account already uses the email', function () {
        User::factory()->create(['email' => 'sara@example.com']);

        withHeader('Accept-Language', 'en')
            ->postJson(route('api.v1.auth.register'), registrationPayload(['email' => 'sara@example.com']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'An account with this email already exists.']);
    });

    it('returns validation messages in Farsi for Farsi and unsupported languages', function (string $acceptLanguage) {
        withHeader('Accept-Language', $acceptLanguage)
            ->postJson(route('api.v1.auth.register'), registrationPayload(['phone_number' => '123']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone_number' => 'شماره موبایل معتبر وارد کنید؛ مانند ۰۹۱۲۳۴۵۶۷۸۹.']);
    })->with([
        'farsi' => 'fa',
        'farsi with region' => 'fa-IR,fa;q=0.9',
        'unsupported language' => 'de',
    ]);

    it('updates an unfinished registration instead of creating a second account', function () {
        User::factory()->unverified()->create(['phone_number' => '09121234567', 'name' => 'Old Name']);

        postJson(route('api.v1.auth.register'), registrationPayload(['name' => 'New Name']))
            ->assertAccepted();

        assertDatabaseCount('users', 1);
        expect(User::query()->sole()->name)->toBe('New Name');
    });

    it('returns 429 when registering again before the resend cooldown ends', function () {
        freezeTime();

        postJson(route('api.v1.auth.register'), registrationPayload())->assertAccepted();

        withHeader('Accept-Language', 'en')
            ->postJson(route('api.v1.auth.register'), registrationPayload())
            ->assertTooManyRequests()
            ->assertJsonPath('code', 'otp_resend_cooldown')
            ->assertJsonPath('retry_after', 60)
            ->assertHeader('Retry-After', '60');

        expect(sms()->countFor('09121234567'))->toBe(1);
    });
});

describe('resend registration code', function () {
    it('sends a new code to an unfinished registration', function () {
        User::factory()->unverified()->create(['phone_number' => '09121234567']);

        postJson(route('api.v1.auth.register.otp'), ['phone_number' => '09121234567'])
            ->assertAccepted()
            ->assertJsonPath('data.phone_number', '09121234567');

        expect(sms()->countFor('09121234567'))->toBe(1);
    });

    it('responds the same way without sending a code for an unknown phone number', function () {
        postJson(route('api.v1.auth.register.otp'), ['phone_number' => '09121234567'])
            ->assertAccepted()
            ->assertJsonPath('data.phone_number', '09121234567');

        expect(sms()->countFor('09121234567'))->toBe(0);
    });

    it('does not send a registration code to a verified account', function () {
        User::factory()->create(['phone_number' => '09121234567']);

        postJson(route('api.v1.auth.register.otp'), ['phone_number' => '09121234567'])->assertAccepted();

        expect(sms()->countFor('09121234567'))->toBe(0);
    });
});

describe('verify registration', function () {
    it('verifies the phone number and returns a working API token', function () {
        postJson(route('api.v1.auth.register'), registrationPayload());

        $response = postJson(route('api.v1.auth.register.verify'), [
            'phone_number' => '09121234567',
            'code' => sms()->lastCodeFor('09121234567'),
        ])
            ->assertOk()
            ->assertJsonPath('data.customer.phone_number', '09121234567')
            ->assertJsonPath('data.customer.name', 'Sara Ahmadi');

        $user = User::query()->sole();

        expect($user->hasVerifiedPhoneNumber())->toBeTrue();
        assertIssuedTokenFor($response, $user);
    });

    it('accepts a code typed with Persian digits', function () {
        postJson(route('api.v1.auth.register'), registrationPayload());
        $persianCode = strtr((string) sms()->lastCodeFor('09121234567'), ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);

        postJson(route('api.v1.auth.register.verify'), ['phone_number' => '09121234567', 'code' => $persianCode])
            ->assertOk();
    });

    it('returns 422 for a wrong code and keeps the account unverified', function () {
        postJson(route('api.v1.auth.register'), registrationPayload());

        withHeader('Accept-Language', 'en')
            ->postJson(route('api.v1.auth.register.verify'), ['phone_number' => '09121234567', 'code' => wrongCodeFor('09121234567')])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'otp_invalid')
            ->assertJsonValidationErrors(['code' => 'The verification code is incorrect.']);

        expect(User::query()->sole()->hasVerifiedPhoneNumber())->toBeFalse();
        assertNoTokenIssued();
    });

    it('returns 422 when the code has expired', function () {
        postJson(route('api.v1.auth.register'), registrationPayload());
        $code = sms()->lastCodeFor('09121234567');

        travel(121)->seconds();

        postJson(route('api.v1.auth.register.verify'), ['phone_number' => '09121234567', 'code' => $code])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'otp_expired');
    });

    it('invalidates the code after five wrong attempts', function () {
        postJson(route('api.v1.auth.register'), registrationPayload());
        $code = (string) sms()->lastCodeFor('09121234567');
        $wrongCode = wrongCodeFor('09121234567');

        foreach (range(1, 4) as $attempt) {
            postJson(route('api.v1.auth.register.verify'), ['phone_number' => '09121234567', 'code' => $wrongCode])
                ->assertJsonPath('code', 'otp_invalid');
        }

        postJson(route('api.v1.auth.register.verify'), ['phone_number' => '09121234567', 'code' => $wrongCode])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'otp_too_many_attempts');

        postJson(route('api.v1.auth.register.verify'), ['phone_number' => '09121234567', 'code' => $code])
            ->assertJsonPath('code', 'otp_invalid');
    });

    it('rejects a code sent earlier once a new code has been sent', function () {
        postJson(route('api.v1.auth.register'), registrationPayload());
        $firstCode = (string) sms()->lastCodeFor('09121234567');

        travel(61)->seconds();
        postJson(route('api.v1.auth.register.otp'), ['phone_number' => '09121234567']);

        if ($firstCode === sms()->lastCodeFor('09121234567')) {
            $this->markTestSkipped('Both random codes happened to be identical.');
        }

        postJson(route('api.v1.auth.register.verify'), ['phone_number' => '09121234567', 'code' => $firstCode])
            ->assertJsonPath('code', 'otp_invalid');
    });
});

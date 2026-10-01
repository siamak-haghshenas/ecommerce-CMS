<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\OtpCode;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\withHeader;

function staff(UserRole $role): User
{
    return User::factory()->withRole($role)->create();
}

function customer(array $attributes = []): User
{
    return User::factory()->withRole(UserRole::Customer)->create($attributes);
}

describe('access', function () {
    it('returns 401 to guests', function () {
        getJson(route('api.v1.admin.customers.index'))->assertUnauthorized();
    });

    it('returns 403 to roles without the permission', function (UserRole $role, string $method, string $routeName) {
        $customer = customer();

        actingAs(staff($role))
            ->json($method, route($routeName, ['customer' => $customer]), ['is_active' => false])
            ->assertForbidden();
    })->with([
        'author listing customers' => [UserRole::Author, 'GET', 'api.v1.admin.customers.index'],
        'customer listing customers' => [UserRole::Customer, 'GET', 'api.v1.admin.customers.index'],
        'admin anonymising a customer' => [UserRole::Admin, 'POST', 'api.v1.admin.customers.anonymise'],
    ]);

    it('returns 404 when the id belongs to a staff member', function () {
        actingAs(staff(UserRole::Admin))
            ->getJson(route('api.v1.admin.customers.show', ['customer' => staff(UserRole::Admin)]))
            ->assertNotFound();
    });
});

describe('list', function () {
    it('lists only customers, newest first, with pagination meta', function () {
        $older = customer();
        $newer = customer();
        staff(UserRole::Author);

        actingAs(staff(UserRole::Admin))
            ->getJson(route('api.v1.admin.customers.index'))
            ->assertOk()
            ->assertJsonPath('data.*.id', [$newer->id, $older->id])
            ->assertJsonPath('meta.total', 2);
    });

    it('searches by phone number typed with Persian digits', function () {
        $match = customer(['phone_number' => '09121112233']);
        customer(['phone_number' => '09350000000']);

        actingAs(staff(UserRole::Admin))
            ->getJson(route('api.v1.admin.customers.index', ['q' => '۱۱۱۲۲']))
            ->assertOk()
            ->assertJsonPath('data.*.id', [$match->id]);
    });

    it('filters by status', function (string $status, string $expected) {
        $customers = [
            'active' => customer(),
            'inactive' => customer(['is_active' => false]),
            'anonymised' => customer(['is_active' => false, 'anonymised_at' => now(), 'phone_number' => null]),
        ];

        actingAs(staff(UserRole::Admin))
            ->getJson(route('api.v1.admin.customers.index', ['status' => $status]))
            ->assertOk()
            ->assertJsonPath('data.*.id', [$customers[$expected]->id]);
    })->with([
        'active' => ['active', 'active'],
        'inactive' => ['inactive', 'inactive'],
        'anonymised' => ['anonymised', 'anonymised'],
    ]);
});

describe('update', function () {
    it('lets an admin edit name, email and language, logging only the changed field names', function () {
        $admin = staff(UserRole::Admin);
        $customer = customer(['name' => 'Old Name', 'email' => null, 'locale' => 'fa']);

        actingAs($admin)
            ->putJson(route('api.v1.admin.customers.update', ['customer' => $customer]), [
                'name' => 'New Name',
                'email' => 'New@Example.com',
                'locale' => 'fa',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.email', 'new@example.com');

        $activity = Activity::query()->sole();

        expect($activity->event)->toBe('customer.updated')
            ->and($activity->causer_id)->toBe($admin->id)
            ->and($activity->properties->toArray())->toBe(['fields' => ['name', 'email']])
            ->and(json_encode($activity->properties))->not->toContain('New Name');
    });

    it('returns 422 when the email belongs to another account', function () {
        customer(['email' => 'taken@example.com']);
        $customer = customer();

        actingAs(staff(UserRole::Admin))
            ->putJson(route('api.v1.admin.customers.update', ['customer' => $customer]), [
                'name' => 'Name',
                'email' => 'taken@example.com',
                'locale' => 'fa',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    });
});

describe('status', function () {
    it('deactivates a customer, blocking their open session', function () {
        $customer = customer();

        actingAs(staff(UserRole::Admin))
            ->putJson(route('api.v1.admin.customers.status', ['customer' => $customer]), ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        expect(Activity::query()->sole()->event)->toBe('customer.deactivated');

        actingAs($customer->refresh())
            ->getJson(route('api.v1.auth.me'))
            ->assertForbidden()
            ->assertJsonPath('code', 'account_inactive');
    });

    it('reactivates a customer', function () {
        $customer = customer(['is_active' => false]);

        actingAs(staff(UserRole::Admin))
            ->putJson(route('api.v1.admin.customers.status', ['customer' => $customer]), ['is_active' => true])
            ->assertOk()
            ->assertJsonPath('data.is_active', true);
    });
});

describe('anonymise', function () {
    it('removes personal data, keeps the account row and logs no personal data', function () {
        $superAdmin = staff(UserRole::SuperAdmin);
        $customer = customer(['name' => 'Sara Ahmadi', 'phone_number' => '09121234567', 'email' => 'sara@example.com']);
        OtpCode::factory()->create(['phone_number' => '09121234567']);
        $customer->createToken('mobile');

        withHeader('Accept-Language', 'en')
            ->actingAs($superAdmin)
            ->postJson(route('api.v1.admin.customers.anonymise', ['customer' => $customer]))
            ->assertOk()
            ->assertJsonPath('data.name', 'Anonymised user')
            ->assertJsonPath('data.phone_number', null)
            ->assertJsonPath('data.email', null)
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.is_anonymised', true);

        $customer->refresh();

        expect($customer->exists)->toBeTrue()
            ->and($customer->name)->not->toBe('Sara Ahmadi')
            ->and($customer->tokens()->count())->toBe(0)
            ->and(OtpCode::query()->where('phone_number', '09121234567')->exists())->toBeFalse()
            ->and(Activity::query()->sole()->event)->toBe('customer.anonymised')
            ->and(Activity::query()->get()->toJson())->not->toContain('Sara')->not->toContain('09121234567');
    });

    it('returns 409 when changing an anonymised customer', function (string $method, string $routeName, array $payload) {
        $customer = customer(['is_active' => false, 'anonymised_at' => now(), 'phone_number' => null]);

        actingAs(staff(UserRole::SuperAdmin))
            ->json($method, route($routeName, ['customer' => $customer]), $payload)
            ->assertConflict()
            ->assertJsonPath('code', 'customer_anonymised');
    })->with([
        'anonymise again' => ['POST', 'api.v1.admin.customers.anonymise', []],
        'reactivate' => ['PUT', 'api.v1.admin.customers.status', ['is_active' => true]],
        'edit' => ['PUT', 'api.v1.admin.customers.update', ['name' => 'X', 'email' => null, 'locale' => 'fa']],
    ]);
});

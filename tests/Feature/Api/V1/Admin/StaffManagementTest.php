<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\postJson;
use function Pest\Laravel\withHeader;

function member(UserRole $role, array $attributes = []): User
{
    return User::factory()->withRole($role)->create($attributes);
}

describe('access', function () {
    it('lets an admin list staff but not change them', function () {
        $admin = member(UserRole::Admin);

        actingAs($admin)->getJson(route('api.v1.admin.staff.index'))->assertOk();

        actingAs($admin)
            ->postJson(route('api.v1.admin.staff.store'), ['phone_number' => '09121234567', 'name' => 'X', 'role' => 'author'])
            ->assertForbidden();
    });

    it('returns 403 to an author listing staff', function () {
        actingAs(member(UserRole::Author))->getJson(route('api.v1.admin.staff.index'))->assertForbidden();
    });

    it('returns 404 when the id belongs to a customer', function () {
        actingAs(member(UserRole::SuperAdmin))
            ->putJson(route('api.v1.admin.staff.role', ['staffMember' => member(UserRole::Customer)]), ['role' => 'admin'])
            ->assertNotFound();
    });
});

it('lists staff members with their role, excluding customers', function () {
    $superAdmin = member(UserRole::SuperAdmin, ['name' => 'A']);
    $author = member(UserRole::Author, ['name' => 'B']);
    member(UserRole::Customer);

    actingAs($superAdmin)
        ->getJson(route('api.v1.admin.staff.index'))
        ->assertOk()
        ->assertJsonPath('data.*.id', [$superAdmin->id, $author->id])
        ->assertJsonPath('data.1.role', 'author');
});

describe('add', function () {
    it('creates a verified staff account without a usable password for a new phone number', function () {
        $superAdmin = member(UserRole::SuperAdmin);

        actingAs($superAdmin)
            ->postJson(route('api.v1.admin.staff.store'), ['phone_number' => '۰۹۱۲۱۲۳۴۵۶۷', 'name' => 'Negar', 'role' => 'author'])
            ->assertCreated()
            ->assertJsonPath('data.role', 'author')
            ->assertJsonPath('data.phone_number', '09121234567');

        $member = User::query()->firstWhere('phone_number', '09121234567');

        expect($member->hasVerifiedPhoneNumber())->toBeTrue()
            ->and($member->primaryRole())->toBe(UserRole::Author)
            ->and(Activity::query()->sole()->properties->toArray())->toBe(['role' => 'author', 'promoted_customer' => false]);
    });

    it('lets a new staff member log in with an SMS code', function () {
        actingAs(member(UserRole::SuperAdmin))
            ->postJson(route('api.v1.admin.staff.store'), ['phone_number' => '09121234567', 'name' => 'Negar', 'role' => 'admin']);

        auth()->guard('web')->logout();

        postJson(route('api.v1.admin.auth.login.otp'), ['phone_number' => '09121234567'])->assertAccepted();
        postJson(route('api.v1.admin.auth.login.otp.verify'), [
            'phone_number' => '09121234567',
            'code' => sms()->lastCodeFor('09121234567'),
        ])->assertOk()->assertJsonPath('data.roles', ['admin']);
    });

    it('promotes an existing customer instead of creating a second account', function () {
        $customer = member(UserRole::Customer, ['phone_number' => '09121234567', 'name' => 'Existing']);

        actingAs(member(UserRole::SuperAdmin))
            ->postJson(route('api.v1.admin.staff.store'), ['phone_number' => '09121234567', 'role' => 'admin'])
            ->assertCreated()
            ->assertJsonPath('data.id', $customer->id)
            ->assertJsonPath('data.name', 'Existing');

        assertDatabaseCount('users', 2);
        expect($customer->refresh()->getRoleNames()->all())->toBe(['admin']);
    });

    it('returns 422 when the number already belongs to a staff member', function () {
        member(UserRole::Author, ['phone_number' => '09121234567']);

        withHeader('Accept-Language', 'en')
            ->actingAs(member(UserRole::SuperAdmin))
            ->postJson(route('api.v1.admin.staff.store'), ['phone_number' => '09121234567', 'role' => 'admin'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'already_staff')
            ->assertJsonValidationErrors(['phone_number' => 'This mobile number already belongs to a staff member.']);
    });

    it('returns 422 when a new account has no name', function () {
        actingAs(member(UserRole::SuperAdmin))
            ->postJson(route('api.v1.admin.staff.store'), ['phone_number' => '09121234567', 'role' => 'admin'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'name_required');

        assertDatabaseCount('users', 1);
    });

    it('rejects the customer role', function () {
        actingAs(member(UserRole::SuperAdmin))
            ->postJson(route('api.v1.admin.staff.store'), ['phone_number' => '09121234567', 'name' => 'X', 'role' => 'customer'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');
    });
});

describe('role and status', function () {
    it('changes a role and logs the before and after roles', function () {
        $author = member(UserRole::Author);

        actingAs(member(UserRole::SuperAdmin))
            ->putJson(route('api.v1.admin.staff.role', ['staffMember' => $author]), ['role' => 'admin'])
            ->assertOk()
            ->assertJsonPath('data.role', 'admin');

        expect(Activity::query()->sole()->properties->toArray())->toBe(['from' => 'author', 'to' => 'admin']);
    });

    it('deactivates a staff member, who then loses panel access', function () {
        $admin = member(UserRole::Admin);

        actingAs(member(UserRole::SuperAdmin))
            ->putJson(route('api.v1.admin.staff.status', ['staffMember' => $admin]), ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        actingAs($admin->refresh())->getJson(route('api.v1.admin.auth.me'))->assertForbidden();
    });

    it('removes staff access, turning the member into a customer', function () {
        $admin = member(UserRole::Admin);

        actingAs(member(UserRole::SuperAdmin))
            ->deleteJson(route('api.v1.admin.staff.destroy', ['staffMember' => $admin]))
            ->assertNoContent();

        expect($admin->refresh()->getRoleNames()->all())->toBe(['customer'])
            ->and(Activity::query()->sole()->event)->toBe('staff.removed');
    });
});

describe('safety rules', function () {
    it('returns 409 when a super admin changes their own account', function (string $method, string $routeName, array $payload) {
        $superAdmin = member(UserRole::SuperAdmin);
        member(UserRole::SuperAdmin);

        actingAs($superAdmin)
            ->json($method, route($routeName, ['staffMember' => $superAdmin]), $payload)
            ->assertConflict()
            ->assertJsonPath('code', 'cannot_change_own_account');
    })->with([
        'role' => ['PUT', 'api.v1.admin.staff.role', ['role' => 'admin']],
        'status' => ['PUT', 'api.v1.admin.staff.status', ['is_active' => false]],
        'removal' => ['DELETE', 'api.v1.admin.staff.destroy', []],
    ]);

    it('refuses to demote, deactivate or remove the last active super admin', function (string $method, string $routeName, array $payload) {
        // Only super admins manage staff, so today this rule is a safety net: it needs a caller who
        // is authorised but not counted as an active super admin. Simulate that by deactivating the
        // caller's row after they are authenticated (the request uses the already-loaded user).
        $target = member(UserRole::SuperAdmin);
        $caller = member(UserRole::SuperAdmin);

        actingAs($caller);
        User::query()->whereKey($caller->id)->update(['is_active' => false]);

        $this->json($method, route($routeName, ['staffMember' => $target]), $payload)
            ->assertConflict()
            ->assertJsonPath('code', 'last_super_admin');
    })->with([
        'demote' => ['PUT', 'api.v1.admin.staff.role', ['role' => 'admin']],
        'deactivate' => ['PUT', 'api.v1.admin.staff.status', ['is_active' => false]],
        'remove' => ['DELETE', 'api.v1.admin.staff.destroy', []],
    ]);

    it('allows demoting a super admin while another active super admin remains', function () {
        $target = member(UserRole::SuperAdmin);

        actingAs(member(UserRole::SuperAdmin))
            ->putJson(route('api.v1.admin.staff.role', ['staffMember' => $target]), ['role' => 'admin'])
            ->assertOk();
    });
});

it('records the last login time', function () {
    $admin = member(UserRole::Admin, ['phone_number' => '09121234567', 'last_login_at' => null]);

    postJson(route('api.v1.admin.auth.login'), ['phone_number' => '09121234567', 'password' => 'password'])->assertOk();

    expect($admin->refresh()->last_login_at)->not->toBeNull();
});

it('shows the activity log to a super admin only', function () {
    $superAdmin = member(UserRole::SuperAdmin);
    $author = member(UserRole::Author);

    actingAs($superAdmin)->putJson(route('api.v1.admin.staff.role', ['staffMember' => $author]), ['role' => 'admin']);

    actingAs($superAdmin)
        ->getJson(route('api.v1.admin.activity-log', ['subject_id' => $author->id]))
        ->assertOk()
        ->assertJsonPath('data.0.event', 'staff.role_changed')
        ->assertJsonPath('data.0.causer.id', $superAdmin->id)
        ->assertJsonPath('data.0.subject.id', $author->id);

    actingAs(member(UserRole::Admin))->getJson(route('api.v1.admin.activity-log'))->assertForbidden();
});

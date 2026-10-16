<?php

declare(strict_types=1);

use App\Actions\Coupons\SyncCouponCustomersAction;
use App\Enums\CouponCustomerScope;
use App\Enums\UserRole;
use App\Http\Requests\Concerns\HasCouponCustomerFields;
use App\Models\Coupon;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\actingAs;

function couponShopper(array $attributes = []): User
{
    return User::factory()->withRole(UserRole::Customer)->create($attributes);
}

/**
 * Runs the coupon save's "who can use it" rules on $data; returns the error messages by field.
 *
 * @return array<string, list<string>>
 */
function couponCustomerErrors(array $data): array
{
    $request = new class extends FormRequest
    {
        use HasCouponCustomerFields;

        /** @return array<string, list<mixed>> */
        public function rules(): array
        {
            return $this->couponCustomerRules();
        }

        /** @return list<Closure> */
        public function after(): array
        {
            return [fn ($validator) => $validator->errors()->isEmpty() ? $this->checkCouponCustomers($validator) : null];
        }
    };

    $request = $request::create('/', 'POST', $data)->setContainer(app())->setRedirector(app('redirect'));

    try {
        $request->validateResolved();
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    return [];
}

describe('who can use it', function () {
    it('needs at least one customer when limited to chosen customers', function () {
        expect(couponCustomerErrors(['customer_scope' => 'selected', 'customer_ids' => []]))->toHaveKey('customer_ids')
            ->and(couponCustomerErrors(['customer_scope' => 'all', 'customer_ids' => []]))->toBe([]);
    });

    it('refuses staff and anonymised accounts but keeps a deactivated customer', function () {
        $inactive = couponShopper(['is_active' => false]);
        $anonymised = couponShopper(['anonymised_at' => now(), 'phone_number' => null]);
        $staffMember = User::factory()->withRole(UserRole::Admin)->create();

        $errors = couponCustomerErrors([
            'customer_scope' => 'selected',
            'customer_ids' => [$inactive->id, $anonymised->id, $staffMember->id],
        ]);

        expect($errors)->not->toHaveKey('customer_ids.0')
            ->and($errors)->toHaveKeys(['customer_ids.1', 'customer_ids.2']);
    });

    it('saves the chosen customers and empties the list for everyone', function () {
        $coupon = Coupon::factory()->create();
        [$sara, $reza] = [couponShopper(), couponShopper()];
        $sync = app(SyncCouponCustomersAction::class);

        expect($sync->handle($coupon, CouponCustomerScope::Selected, [$sara->id, $reza->id]))->toBeTrue()
            ->and($coupon->fresh()->customer_scope)->toBe(CouponCustomerScope::Selected)
            ->and($coupon->customers()->pluck('users.id')->sort()->values()->all())->toBe([$sara->id, $reza->id])
            ->and($coupon->isFor($sara))->toBeTrue()
            ->and($coupon->isFor(couponShopper()))->toBeFalse()
            ->and($sync->handle($coupon, CouponCustomerScope::Selected, [$reza->id, $sara->id]))->toBeFalse();

        expect($sync->handle($coupon, CouponCustomerScope::All, [$sara->id]))->toBeTrue()
            ->and($coupon->customers()->count())->toBe(0)
            ->and($coupon->isFor(couponShopper()))->toBeTrue();
    });

    it('removes an anonymised customer from every coupon', function () {
        $customer = couponShopper();
        $coupon = Coupon::factory()->forSelectedCustomers()->create();
        $coupon->customers()->attach($customer);

        actingAs(User::factory()->withRole(UserRole::SuperAdmin)->create())
            ->postJson(route('api.v1.admin.customers.anonymise', ['customer' => $customer]))
            ->assertOk();

        expect($coupon->customers()->count())->toBe(0);
    });
});

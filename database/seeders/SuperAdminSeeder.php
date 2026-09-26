<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    /**
     * Create (or update) the super admin from the SUPER_ADMIN_* environment values.
     */
    public function run(): void
    {
        $phoneNumber = (string) config('auth.super_admin.phone_number');
        $password = (string) config('auth.super_admin.password');

        if ($phoneNumber === '' || $password === '') {
            $this->command->warn('Super admin skipped: set SUPER_ADMIN_PHONE_NUMBER and SUPER_ADMIN_PASSWORD in .env.');

            return;
        }

        $user = User::query()->updateOrCreate(
            ['phone_number' => PhoneNumber::normalize($phoneNumber)],
            [
                'name' => config('auth.super_admin.name'),
                'password' => $password,
            ],
        );

        $user->forceFill(['phone_verified_at' => $user->phone_verified_at ?? now(), 'is_active' => true])->save();

        $user->syncRoles(UserRole::SuperAdmin);
    }
}

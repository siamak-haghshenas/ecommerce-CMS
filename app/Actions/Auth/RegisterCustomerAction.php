<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Data\Auth\RegisterCustomerData;
use App\Enums\OtpPurpose;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class RegisterCustomerAction
{
    public function __construct(private SendOtpAction $sendOtp) {}

    /**
     * Create an unverified customer (or refresh an unfinished registration) and send
     * the verification code. The account becomes usable once the phone is verified.
     */
    public function handle(RegisterCustomerData $data, ?string $ipAddress = null): User
    {
        $user = DB::transaction(function () use ($data): User {
            $user = User::query()
                ->where('phone_number', $data->phoneNumber)
                ->whereNull('phone_verified_at')
                ->first() ?? new User;

            $user->fill([
                'name' => $data->name,
                'phone_number' => $data->phoneNumber,
                'email' => $data->email,
                'password' => $data->password,
                'locale' => $data->locale,
            ]);
            // Saves and refreshes updated_at, restarting the unverified-registration prune timer.
            $user->touch();

            $user->syncRoles(UserRole::Customer);

            return $user;
        });

        $this->sendOtp->handle($data->phoneNumber, OtpPurpose::Register, ipAddress: $ipAddress);

        return $user;
    }
}

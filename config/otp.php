<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | One-Time Password Settings
    |--------------------------------------------------------------------------
    |
    | Codes are sent by SMS for registration, login and password resets. Only
    | a hash of each code is stored, and every code is single-use.
    |
    */

    'length' => 5,

    'ttl_seconds' => 120,

    'max_attempts' => 5,

    'resend_cooldown_seconds' => 60,

    'max_sends_per_hour_per_phone' => 5,

    'max_sends_per_hour_per_ip' => 20,

    'max_verifications_per_minute' => 10,

    /*
    |--------------------------------------------------------------------------
    | Unverified Registrations
    |--------------------------------------------------------------------------
    |
    | Registrations whose phone number was never verified are pruned after
    | this many hours.
    |
    */

    'unverified_user_ttl_hours' => 24,

];

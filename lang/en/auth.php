<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used during authentication for various
    | messages that we need to display to the user. You are free to modify
    | these language lines according to your application's requirements.
    |
    */

    'failed' => 'These credentials do not match our records.',
    'password' => 'The provided password is incorrect.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',

    'errors' => [
        'invalid_credentials' => 'The mobile number or password is incorrect.',
        'phone_not_verified' => 'Your mobile number has not been verified yet.',
        'account_inactive' => 'Your account has been deactivated. Please contact support.',
        'not_staff' => 'You do not have access to the admin panel.',
        'otp_invalid' => 'The verification code is incorrect.',
        'otp_expired' => 'The verification code has expired. Please request a new one.',
        'otp_too_many_attempts' => 'Too many incorrect attempts. Please request a new code.',
        'otp_resend_cooldown' => 'Please wait :seconds seconds before requesting a new code.',
    ],

];

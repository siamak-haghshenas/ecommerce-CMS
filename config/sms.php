<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default SMS Driver
    |--------------------------------------------------------------------------
    |
    | The driver used to deliver text messages. "log" writes messages to the
    | application log for local development, "array" keeps them in memory for
    | tests, and "faraz" delivers them through the Faraz SMS (IPPanel) API.
    |
    | Supported: "log", "array", "faraz"
    |
    */

    'default' => env('SMS_DRIVER', 'log'),

    'drivers' => [

        'log' => [
            'channel' => env('SMS_LOG_CHANNEL'),
        ],

        'faraz' => [
            'base_url' => env('FARAZ_SMS_BASE_URL', 'https://edge.ippanel.com/v1'),
            'api_key' => env('FARAZ_SMS_API_KEY'),
            'from_number' => env('FARAZ_SMS_FROM_NUMBER'),
            'patterns' => [
                'otp' => env('FARAZ_SMS_OTP_PATTERN'),
            ],
            'connect_timeout' => 3,
            'timeout' => 10,
        ],

    ],

];

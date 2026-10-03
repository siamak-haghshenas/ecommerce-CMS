<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default AI Driver
    |--------------------------------------------------------------------------
    |
    | The provider behind AI features such as category SEO suggestions. "claude"
    | calls the Anthropic Messages API; "fake" returns canned answers for tests.
    | Anthropic does not serve requests from Iranian IP addresses, so the
    | server must be hosted outside Iran for the "claude" driver to work.
    |
    | Supported: "claude", "fake"
    |
    */

    'default' => env('AI_DRIVER', 'claude'),

    'drivers' => [

        'claude' => [
            'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
            'api_key' => env('ANTHROPIC_API_KEY'),
            'model' => env('AI_MODEL', 'claude-sonnet-5'),
            'version' => '2023-06-01',
            'max_tokens' => 1500,
            'connect_timeout' => 5,
            'timeout' => 45,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Limits
    |--------------------------------------------------------------------------
    |
    | Every request is a paid API call. Both limits apply (rate limiter "ai").
    |
    */

    'limits' => [
        'per_user_per_hour' => (int) env('AI_LIMIT_PER_USER_PER_HOUR', 20),
        'per_day' => (int) env('AI_LIMIT_PER_DAY', 200),
    ],

];

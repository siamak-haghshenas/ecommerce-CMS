<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | External storefronts call the API from their own domains. List them in
    | CORS_ALLOWED_ORIGINS (comma separated); "*" allows any origin.
    |
    | Storefronts authenticate with Bearer tokens, never cookies, so credentials
    | are not shared cross-origin. The admin panel is same-origin and unaffected.
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', '*'))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Retry-After'],

    'max_age' => 3600,

    'supports_credentials' => false,

];

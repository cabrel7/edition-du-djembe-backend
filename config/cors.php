<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_merge(
        [
            'https://leseditionsdudjembe.com',
            'https://api.leseditionsdudjembe.com',
            'https://admin.leseditionsdudjembe.com',
            'https://www.leseditionsdudjembe.com',
        ],
        // Origines de développement uniquement (ex. CORS_DEV_ORIGINS="http://localhost:8080").
        array_map('trim', explode(',', (string) env('CORS_DEV_ORIGINS', '')))
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];

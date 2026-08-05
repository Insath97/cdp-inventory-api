<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Configure allowed origins via the CORS_ALLOWED_ORIGINS environment
    | variable (comma-separated list of exact origins). Do NOT use wildcard
    | private-IP regex patterns in production — they bypass CORS protection
    | for any device on the same network.
    |
    | Example .env entries:
    |   CORS_ALLOWED_ORIGINS=https://app.yourcompany.com
    |   # For local dev only:
    |   CORS_ALLOWED_ORIGINS=http://localhost:5173,http://127.0.0.1:5173
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Exact origins from .env — no private-IP wildcard patterns
    'allowed_origins' => array_filter(
        array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173,http://localhost:3000,http://127.0.0.1:5173,http://127.0.0.1:3000')))
    ),

    // No wildcard regex patterns — origins must be listed explicitly in CORS_ALLOWED_ORIGINS
    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 7200,

    'supports_credentials' => true,

];

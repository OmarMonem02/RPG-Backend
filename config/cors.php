<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env(
            'CORS_ALLOWED_ORIGINS',
            'http://localhost:3000,http://localhost:3001,http://127.0.0.1:3000,http://127.0.0.1:3001,https://rpg-frontend-woad.vercel.app'
        ))
    ))),

    // Regex patterns for origins. Defaults to matching every Vercel
    // deployment of the rpg-frontend project (production + preview URLs
    // like https://rpg-frontend-<hash>-<team>.vercel.app).
    'allowed_origins_patterns' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env(
            'CORS_ALLOWED_ORIGINS_PATTERNS',
            '#^https://rpg-frontend(-[a-z0-9-]+)?\.vercel\.app$#'
        ))
    ))),

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];

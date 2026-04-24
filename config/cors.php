<?php

$allowedOrigins = array_values(array_filter(array_unique(array_map(
    static fn (string $origin) => trim($origin),
    array_merge(
        [env('FRONTEND_URL', 'http://localhost:4200')],
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'http://localhost:4200'))
    )
))));

return [
    'paths' => ['api/*', 'mexpres-atencion-backend/api/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => $allowedOrigins,
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => ['set-cookie'],
    'max_age' => 0,
    'supports_credentials' => filter_var(env('CORS_SUPPORTS_CREDENTIALS', true), FILTER_VALIDATE_BOOL),
];

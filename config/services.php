<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'sso' => [
        'auth_server_base_url' => env('SSO_AUTH_SERVER_BASE_URL'),
        'shared_secret' => env('SSO_SHARED_SECRET'),
        'resolve_secret' => env('SSO_RESOLVE_SECRET'),
        'audience' => env('SSO_AUDIENCE'),
        'issuer' => env('SSO_ISSUER'),
        'issuers' => array_values(array_filter(array_map(
            static fn (string $issuer) => trim($issuer),
            explode(',', (string) env('SSO_ISSUERS', ''))
        ))),
        'resolve_url' => env('SSO_RESOLVE_URL'),
        'resolve_endpoint' => env('SSO_RESOLVE_ENDPOINT', '/api/sso/resolve'),
        'resolve_endpoints' => array_values(array_filter(array_map(
            static fn (string $endpoint) => trim($endpoint),
            explode(',', (string) env('SSO_RESOLVE_ENDPOINTS', ''))
        ))),
        'timeout' => (int) env('SSO_TIMEOUT', 15),
    ],

    'pide' => [
        'base_url' => env('PIDE_BASE_URL') ?: 'https://ws2.pide.gob.pe/Rest/RENIEC/Consultar',
        'token' => env('PIDE_TOKEN'),
        'timeout' => (int) env('PIDE_TIMEOUT', 15),
    ],

];

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
        'token' => env('POSTMARK_TOKEN'),
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

    'opendota' => [
        'base_url' => env('OPENDOTA_BASE_URL', 'https://api.opendota.com/api'),
        'api_key' => env('OPENDOTA_API_KEY'),
    ],

    'steam' => [
        'client_id' => env('STEAM_CLIENT_ID', env('STEAM_API_KEY')),
        'client_secret' => env('STEAM_API_KEY'),
        'redirect' => env('STEAM_REDIRECT_URI', '/auth/steam/callback'),
        'base_url' => env('STEAM_BASE_URL', 'https://api.steampowered.com'),
        'api_key' => env('STEAM_API_KEY'),
        'community_url' => env('STEAM_COMMUNITY_URL', 'https://steamcommunity.com'),
    ],

    'stratz' => [
        'endpoint' => env('STRATZ_ENDPOINT', 'https://api.stratz.com/graphql'),
        'token' => env('STRATZ_TOKEN'),
    ],

];

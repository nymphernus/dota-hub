<?php

$appUrl = (string) env('APP_URL', 'http://localhost');
$steamRedirect = (string) env('STEAM_REDIRECT_URI', '/auth/steam/callback');
$steamRealmUrl = parse_url($steamRedirect, PHP_URL_HOST) ? $steamRedirect : $appUrl;
$steamRealmHost = parse_url($steamRealmUrl, PHP_URL_HOST) ?: 'localhost';
$steamRealmPort = parse_url($steamRealmUrl, PHP_URL_PORT);
$steamRealmPath = parse_url($steamRealmUrl, PHP_URL_PATH) ?: '';
// Steam accepts the callback path as the realm. A bare host is rejected with
// "Access Denied" for local/public HTTP callbacks.
$steamRealm = $steamRealmHost
    .($steamRealmPort && ! in_array((int) $steamRealmPort, [80, 443], true) ? ':'.$steamRealmPort : '')
    .rtrim($steamRealmPath, '/');
$steamAllowedHosts = array_values(array_unique(array_filter([
    parse_url($appUrl, PHP_URL_HOST),
    parse_url($steamRedirect, PHP_URL_HOST),
])));

if ($steamAllowedHosts === []) {
    $steamAllowedHosts = ['localhost'];
}

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
        'redirect' => $steamRedirect,
        // Steam rejects a login when openid.realm does not match return_to.
        'realm' => env('STEAM_REALM') ?: $steamRealm,
        // The provider recomputes the default realm from the resolved redirect
        // URL so relative STEAM_REDIRECT_URI values keep the callback path.
        'realm_is_default' => ! env('STEAM_REALM'),
        'force_https' => env('STEAM_FORCE_HTTPS', parse_url($steamRedirect, PHP_URL_SCHEME) === 'https'),
        // Socialite checks the parsed return_to host against this list.
        'allowed_hosts' => $steamAllowedHosts,
        'base_url' => env('STEAM_BASE_URL', 'https://api.steampowered.com'),
        'api_key' => env('STEAM_API_KEY'),
        'community_url' => env('STEAM_COMMUNITY_URL', 'https://steamcommunity.com'),
    ],

    'stratz' => [
        'endpoint' => env('STRATZ_ENDPOINT', 'https://api.stratz.com/graphql'),
        'token' => env('STRATZ_API_KEY', env('STRATZ_TOKEN')),
    ],

];

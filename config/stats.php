<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cache lifetimes (seconds)
    |--------------------------------------------------------------------------
    |
    | External APIs have strict rate limits (OpenDota allows 2000 requests per
    | day). Everything rendered on a profile page is cached in Redis so repeat
    | visits never hit the upstream API.
    |
    */

    'cache' => [
        'dota_profile' => (int) env('OPENDOTA_CACHE_TTL', 1800),
        'dota_match' => (int) env('OPENDOTA_MATCH_CACHE_TTL', 3600),
        'dota_heroes' => (int) env('OPENDOTA_HEROES_CACHE_TTL', 86400),
        'dota_items' => (int) env('OPENDOTA_ITEMS_CACHE_TTL', 86400),
        'steam_profile' => (int) env('STEAM_CACHE_TTL', 1800),
        'steam_vanity' => (int) env('STEAM_VANITY_CACHE_TTL', 604800),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate limiting
    |--------------------------------------------------------------------------
    */

    'opendota_daily_limit' => (int) env('OPENDOTA_DAILY_LIMIT', 2000),

    /*
    |--------------------------------------------------------------------------
    | Recently searched players shown on the home page
    |--------------------------------------------------------------------------
    */

    'recent_searches' => (int) env('RECENT_SEARCHES_COUNT', 5),

];

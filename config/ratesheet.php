<?php

// Create config/ratesheet.php

return [
    /*
    |--------------------------------------------------------------------------
    | Rate Sheet Engine Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration options for the enhanced rate calculation engine
    |
    */

    // Cache TTL in seconds (default: 1 hour)
    'cache_ttl' => env('RATESHEET_CACHE_TTL', 3600),

    // Feature flag to enable/disable new rate engine
    'use_new_engine' => env('RATESHEET_USE_NEW_ENGINE', true),

    // Enable debug mode to return calculation details
    'debug_mode' => env('RATESHEET_DEBUG_MODE', false),

    // Default fallback bracket for skid pieces when no numeric brackets exist
    'default_skid_bracket' => env('RATESHEET_DEFAULT_SKID_BRACKET', 'ltl'),

    // Cache lock timeout in seconds for concurrent rebuilds
    'cache_lock_timeout' => env('RATESHEET_CACHE_LOCK_TIMEOUT', 10),

    // Logging configuration
    'logging' => [
        'enabled' => env('RATESHEET_LOGGING_ENABLED', true),
        'level' => env('RATESHEET_LOGGING_LEVEL', 'info'), // debug, info, warning, error
        'log_cache_builds' => env('RATESHEET_LOG_CACHE_BUILDS', false),
        'log_rate_calculations' => env('RATESHEET_LOG_RATE_CALCULATIONS', false),
    ],
];
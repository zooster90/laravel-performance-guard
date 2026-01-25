<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enable Performance Monitoring
    |--------------------------------------------------------------------------
    |
    | Toggle performance monitoring on or off. When disabled, the middleware
    | will pass through without recording any metrics.
    |
    */
    'enabled' => env('PERFORMANCE_GUARD_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Sampling Rate
    |--------------------------------------------------------------------------
    |
    | The percentage of requests to monitor (0.0 to 1.0). Use a lower value
    | in production to reduce overhead. 1.0 = monitor every request.
    |
    */
    'sampling_rate' => env('PERFORMANCE_GUARD_SAMPLING_RATE', 1.0),

    /*
    |--------------------------------------------------------------------------
    | Thresholds
    |--------------------------------------------------------------------------
    |
    | Define thresholds for detecting performance issues.
    |
    */
    'thresholds' => [
        'n_plus_one' => env('PERFORMANCE_GUARD_N_PLUS_ONE_THRESHOLD', 10),
        'slow_query_ms' => env('PERFORMANCE_GUARD_SLOW_QUERY_MS', 300),
        'slow_request_ms' => env('PERFORMANCE_GUARD_SLOW_REQUEST_MS', 1000),
        'memory_mb' => env('PERFORMANCE_GUARD_MEMORY_MB', 128),
        'query_count' => env('PERFORMANCE_GUARD_QUERY_COUNT', 50),
        'max_queries_per_request' => env('PERFORMANCE_GUARD_MAX_QUERIES', 1000),
        'max_sql_length' => env('PERFORMANCE_GUARD_MAX_SQL_LENGTH', 8000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Grading
    |--------------------------------------------------------------------------
    |
    | Define the grading scale based on request duration in milliseconds.
    |
    */
    'grading' => [
        'A' => 200,   // <= 200ms
        'B' => 500,   // <= 500ms
        'C' => 1000,  // <= 1000ms
        'D' => 3000,  // <= 3000ms
        // Everything above 3000ms = F
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    |
    | Configure the built-in performance dashboard.
    |
    */
    'dashboard' => [
        'enabled' => env('PERFORMANCE_GUARD_DASHBOARD', true),
        'path' => 'performance-guard',
        'middleware' => ['web'],
        'auth' => env('PERFORMANCE_GUARD_DASHBOARD_AUTH', true),
        'gate' => 'viewPerformanceGuard',
        'allowed_ips' => [],
        'allowed_emails' => [],
        'cache_ttl' => env('PERFORMANCE_GUARD_DASHBOARD_CACHE_TTL', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    |
    | Configure notification channels for performance alerts.
    |
    */
    'notifications' => [
        'enabled' => env('PERFORMANCE_GUARD_NOTIFICATIONS', false),

        'channels' => [
            'slack' => [
                'enabled' => false,
                'webhook_url' => env('PERFORMANCE_GUARD_SLACK_WEBHOOK'),
            ],
            'email' => [
                'enabled' => false,
                'recipients' => [],
            ],
            'telegram' => [
                'enabled' => false,
                'bot_token' => env('PERFORMANCE_GUARD_TELEGRAM_TOKEN'),
                'chat_id' => env('PERFORMANCE_GUARD_TELEGRAM_CHAT_ID'),
            ],
        ],

        'notify_on' => [
            'n_plus_one' => true,
            'slow_query' => true,
            'slow_request' => true,
            'high_memory' => true,
            'grade_f' => true,
        ],

        'cooldown_minutes' => 15,
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | Configure how performance data is stored and retained.
    |
    */
    'storage' => [
        'connection' => env('PERFORMANCE_GUARD_DB_CONNECTION', null),
        'retention_days' => env('PERFORMANCE_GUARD_RETENTION_DAYS', 30),
        'async' => env('PERFORMANCE_GUARD_ASYNC', true),
        'queue' => env('PERFORMANCE_GUARD_QUEUE', 'default'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Privacy
    |--------------------------------------------------------------------------
    |
    | Configure privacy settings for recorded data.
    |
    */
    'privacy' => [
        'store_ip' => env('PERFORMANCE_GUARD_STORE_IP', true),
        'redact_bindings' => env('PERFORMANCE_GUARD_REDACT_BINDINGS', true),
        'redact_patterns' => [
            '/password/i',
            '/secret/i',
            '/token/i',
            '/api_key/i',
            '/credit_card/i',
            '/ssn/i',
        ],
        'exclude_paths' => [
            '_debugbar/*',
            'telescope/*',
            'horizon/*',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Ignored Routes
    |--------------------------------------------------------------------------
    |
    | Routes that should never be monitored.
    |
    */
    'ignored_routes' => [
        'health',
        'livewire/*',
    ],

];

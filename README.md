# Laravel Performance Guard

Production-safe performance monitoring for Laravel. Catch N+1 queries, slow queries, and performance issues before your users do.

## Features

- **N+1 Query Detection** - Automatically detects N+1 query patterns and suggests eager loading fixes
- **Slow Query Monitoring** - Identifies slow database queries with optimization suggestions
- **Performance Grading** - Grades every request A-F based on response time
- **Built-in Dashboard** - Dark-themed dashboard to visualize performance metrics
- **Notifications** - Alerts via Slack, Email, or Telegram when issues are detected
- **Queue Support** - Stores metrics asynchronously to avoid impacting request performance
- **Privacy First** - Automatically redacts sensitive data from recorded queries
- **Sampling** - Configurable sampling rate for high-traffic production environments
- **Auto Cleanup** - Artisan command to purge old records with configurable retention

## Requirements

- PHP 8.1+
- Laravel 10, 11, or 12

## Installation

```bash
composer require zufarmarwah/laravel-performance-guard
```

Publish the config and migrations:

```bash
php artisan vendor:publish --tag=performance-guard-config
php artisan vendor:publish --tag=performance-guard-migrations
php artisan migrate
```

## Quick Start

Add the middleware to any route or group you want to monitor:

```php
// In a route group
Route::middleware(['performance-guard'])->group(function () {
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/posts', [PostController::class, 'index']);
});

// On a single route
Route::get('/dashboard', DashboardController::class)
    ->middleware('performance-guard');
```

That's it. Visit `/performance-guard` in your browser to see the dashboard.

## Configuration

The config file (`config/performance-guard.php`) provides full control:

```php
return [
    // Toggle monitoring on/off
    'enabled' => env('PERFORMANCE_GUARD_ENABLED', true),

    // Monitor a percentage of requests (0.0 to 1.0)
    'sampling_rate' => env('PERFORMANCE_GUARD_SAMPLING_RATE', 1.0),

    'thresholds' => [
        'n_plus_one' => 10,       // Duplicate query count to trigger N+1 alert
        'slow_query_ms' => 300,   // Query duration threshold in ms
        'slow_request_ms' => 1000, // Request duration threshold in ms
        'memory_mb' => 128,        // Memory usage threshold
        'query_count' => 50,       // Total query count threshold
    ],

    // Performance grading scale (duration in ms)
    'grading' => [
        'A' => 200,   // <= 200ms
        'B' => 500,   // <= 500ms
        'C' => 1000,  // <= 1000ms
        'D' => 3000,  // <= 3000ms
        // Everything above = F
    ],

    'dashboard' => [
        'enabled' => true,
        'path' => 'performance-guard',
        'middleware' => ['web'],
        'auth' => true,              // Require authentication
        'allowed_ips' => [],          // IP whitelist (empty = disabled)
        'allowed_emails' => [],       // Email whitelist (empty = disabled)
    ],

    'notifications' => [
        'enabled' => false,
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
        'cooldown_minutes' => 15, // Prevent alert spam
    ],

    'storage' => [
        'connection' => null,          // Use default DB connection
        'retention_days' => 30,        // Auto-cleanup threshold
        'async' => true,               // Store via queue
        'queue' => 'default',          // Queue name
    ],

    'privacy' => [
        'redact_bindings' => true,     // Redact sensitive query values
        'redact_patterns' => [         // Patterns to trigger redaction
            '/password/i',
            '/secret/i',
            '/token/i',
            '/api_key/i',
        ],
        'exclude_paths' => [           // Never monitor these paths
            '_debugbar/*',
            'telescope/*',
        ],
    ],

    'ignored_routes' => [
        'health',
        'livewire/*',
    ],
];
```

## Dashboard

The built-in dashboard is available at `/performance-guard` (configurable) and shows:

- **Overview** - Total requests, average duration, query counts, memory usage, grade distribution
- **N+1 Issues** - All requests where N+1 patterns were detected
- **Slow Queries** - All requests containing slow database queries
- **Period Filtering** - View data for last 1 hour, 24 hours, 7 days, or 30 days

### Dashboard Access Control

By default, the dashboard requires authentication. You can also restrict access by IP or email:

```php
'dashboard' => [
    'auth' => true,
    'allowed_ips' => ['127.0.0.1', '10.0.0.1'],
    'allowed_emails' => ['admin@example.com'],
],
```

Set `auth` to `false` for open access (not recommended in production).

## API Endpoints

All dashboard data is available via JSON:

- `GET /performance-guard/api` - Overview stats with grade distribution
- `GET /performance-guard/api/{uuid}` - Single record with all queries
- `GET /performance-guard/n-plus-one` - N+1 issues (accepts JSON)
- `GET /performance-guard/slow-queries` - Slow query records (accepts JSON)

## Notifications

Enable alerts to get notified when performance issues occur:

```env
PERFORMANCE_GUARD_NOTIFICATIONS=true
PERFORMANCE_GUARD_SLACK_WEBHOOK=https://hooks.slack.com/services/...
```

Notification types:

- N+1 queries detected
- Slow queries detected
- Slow requests (exceeding threshold)
- Grade F requests

A cooldown period prevents alert spam for the same issue.

## Cleanup

Remove old performance records:

```bash
# Use configured retention (default: 30 days)
php artisan performance-guard:cleanup

# Custom retention period
php artisan performance-guard:cleanup --days=7

# Skip confirmation
php artisan performance-guard:cleanup --force
```

Schedule it in your `app/Console/Kernel.php`:

```php
$schedule->command('performance-guard:cleanup --force')->daily();
```

## Facade

Use the `PerformanceGuard` facade for programmatic access:

```php
use Zufarmarwah\PerformanceGuard\Facades\PerformanceGuard;

// Get stats for a period
$stats = PerformanceGuard::getStats('24h');

// Get grade distribution
$grades = PerformanceGuard::getGradeDistribution('7d');

// Enable/disable monitoring at runtime
PerformanceGuard::disable();
PerformanceGuard::enable();

// Check if monitoring is active
if (PerformanceGuard::isEnabled()) {
    // ...
}
```

## How It Works

1. The `performance-guard` middleware wraps each request
2. A `QueryListener` captures every SQL query via `DB::listen()`
3. After the response is sent:
   - `NPlusOneAnalyzer` detects duplicate query patterns
   - `SlowQueryAnalyzer` finds queries exceeding the threshold
   - `PerformanceScorer` grades the request (A-F) based on duration
4. Results are stored asynchronously via a queued job
5. Notifications are dispatched if configured (with cooldown to prevent spam)

## Testing

```bash
composer test
```

## License

MIT License. See [LICENSE](LICENSE) for details.

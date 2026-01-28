<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Zufarmarwah\PerformanceGuard\Models\PerformanceRecord;

beforeEach(function () {
    $this->artisan('migrate');
});

it('shows status with no data', function () {
    $this->artisan('performance-guard:status')
        ->expectsOutputToContain('Performance Guard')
        ->assertExitCode(0);
});

it('shows status with data', function () {
    PerformanceRecord::create([
        'uuid' => Str::uuid()->toString(),
        'method' => 'GET',
        'uri' => '/api/users',
        'action' => 'UserController@index',
        'duration_ms' => 250.5,
        'query_count' => 8,
        'slow_query_count' => 0,
        'memory_mb' => 32.1,
        'grade' => 'B',
        'has_n_plus_one' => false,
        'has_slow_queries' => false,
        'ip_address' => '127.0.0.1',
    ]);

    $this->artisan('performance-guard:status')
        ->expectsOutputToContain('Performance Guard')
        ->assertExitCode(0);
});

it('accepts period option', function () {
    $this->artisan('performance-guard:status', ['--period' => '7d'])
        ->expectsOutputToContain('7 Days')
        ->assertExitCode(0);
});

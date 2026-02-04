<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Zufarmarwah\PerformanceGuard\Models\PerformanceRecord;

beforeEach(function () {
    $this->artisan('migrate');
});

function createCheckRecord(array $overrides = []): PerformanceRecord
{
    return PerformanceRecord::create(array_merge([
        'uuid' => Str::uuid()->toString(),
        'method' => 'GET',
        'uri' => '/api/users',
        'query_count' => 5,
        'slow_query_count' => 0,
        'duration_ms' => 100.0,
        'memory_mb' => 10.0,
        'grade' => 'A',
        'has_n_plus_one' => false,
        'has_slow_queries' => false,
        'ip_address' => '127.0.0.1',
        'created_at' => now(),
    ], $overrides));
}

it('passes when all routes are within budget', function () {
    createCheckRecord(['duration_ms' => 100.0, 'query_count' => 5]);

    $this->artisan('performance-guard:check', ['--max-duration' => 500, '--max-queries' => 30])
        ->assertExitCode(0);
});

it('fails when a route exceeds duration budget', function () {
    createCheckRecord(['duration_ms' => 1500.0, 'query_count' => 5]);

    $this->artisan('performance-guard:check', ['--max-duration' => 500, '--max-queries' => 30])
        ->assertExitCode(1);
});

it('fails when a route exceeds query budget', function () {
    createCheckRecord(['duration_ms' => 100.0, 'query_count' => 50]);

    $this->artisan('performance-guard:check', ['--max-duration' => 500, '--max-queries' => 30])
        ->assertExitCode(1);
});

it('fails on n-plus-one when flag is set', function () {
    createCheckRecord(['has_n_plus_one' => true]);

    $this->artisan('performance-guard:check', [
        '--max-duration' => 500,
        '--max-queries' => 30,
        '--fail-on-n-plus-one' => true,
    ])
        ->assertExitCode(1);
});

it('succeeds with no data', function () {
    $this->artisan('performance-guard:check')
        ->assertExitCode(0);
});

it('filters by route', function () {
    createCheckRecord(['uri' => '/api/users', 'duration_ms' => 100.0]);
    createCheckRecord(['uri' => '/api/slow', 'duration_ms' => 5000.0]);

    $this->artisan('performance-guard:check', [
        '--route' => '/api/users',
        '--max-duration' => 500,
    ])
        ->assertExitCode(0);
});

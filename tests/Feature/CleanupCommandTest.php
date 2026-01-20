<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Zufarmarwah\PerformanceGuard\Models\PerformanceRecord;

beforeEach(function () {
    $this->artisan('migrate');
});

it('reports when no old records exist', function () {
    $this->artisan('performance-guard:cleanup', ['--force' => true])
        ->expectsOutput('No records older than 30 days found.')
        ->assertSuccessful();
});

it('deletes records older than specified days', function () {
    PerformanceRecord::create([
        'uuid' => Str::uuid()->toString(),
        'method' => 'GET',
        'uri' => '/old-route',
        'query_count' => 5,
        'slow_query_count' => 0,
        'duration_ms' => 100.0,
        'memory_mb' => 10.0,
        'grade' => 'A',
        'has_n_plus_one' => false,
        'has_slow_queries' => false,
        'ip_address' => '127.0.0.1',
        'created_at' => now()->subDays(45),
    ]);

    PerformanceRecord::create([
        'uuid' => Str::uuid()->toString(),
        'method' => 'GET',
        'uri' => '/new-route',
        'query_count' => 3,
        'slow_query_count' => 0,
        'duration_ms' => 50.0,
        'memory_mb' => 5.0,
        'grade' => 'A',
        'has_n_plus_one' => false,
        'has_slow_queries' => false,
        'ip_address' => '127.0.0.1',
        'created_at' => now()->subDays(5),
    ]);

    expect(PerformanceRecord::count())->toBe(2);

    $this->artisan('performance-guard:cleanup', ['--force' => true])
        ->assertSuccessful();

    expect(PerformanceRecord::count())->toBe(1);
    expect(PerformanceRecord::first()->uri)->toBe('/new-route');
});

it('accepts custom retention days', function () {
    PerformanceRecord::create([
        'uuid' => Str::uuid()->toString(),
        'method' => 'GET',
        'uri' => '/test',
        'query_count' => 1,
        'slow_query_count' => 0,
        'duration_ms' => 100.0,
        'memory_mb' => 10.0,
        'grade' => 'B',
        'has_n_plus_one' => false,
        'has_slow_queries' => false,
        'ip_address' => '127.0.0.1',
        'created_at' => now()->subDays(10),
    ]);

    $this->artisan('performance-guard:cleanup', ['--days' => 7, '--force' => true])
        ->assertSuccessful();

    expect(PerformanceRecord::count())->toBe(0);
});

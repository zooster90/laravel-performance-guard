<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Zufarmarwah\PerformanceGuard\Models\PerformanceRecord;
use Zufarmarwah\PerformanceGuard\PerformanceGuardManager;

beforeEach(function () {
    $this->manager = new PerformanceGuardManager;
    $this->artisan('migrate');
});

it('reports enabled status from config', function () {
    config(['performance-guard.enabled' => true]);
    expect($this->manager->isEnabled())->toBeTrue();

    config(['performance-guard.enabled' => false]);
    expect($this->manager->isEnabled())->toBeFalse();
});

it('can enable monitoring', function () {
    config(['performance-guard.enabled' => false]);
    $this->manager->enable();

    expect(config('performance-guard.enabled'))->toBeTrue();
});

it('can disable monitoring', function () {
    config(['performance-guard.enabled' => true]);
    $this->manager->disable();

    expect(config('performance-guard.enabled'))->toBeFalse();
});

it('returns zero stats when no data exists', function () {
    $stats = $this->manager->getStats('24h');

    expect($stats['total_requests'])->toBe(0);
    expect($stats['avg_duration_ms'])->toBe(0.0);
    expect($stats['avg_queries'])->toBe(0.0);
    expect($stats['n_plus_one_count'])->toBe(0);
    expect($stats['slow_query_count'])->toBe(0);
    expect($stats['avg_memory_mb'])->toBe(0.0);
});

it('returns correct stats from database aggregation', function () {
    PerformanceRecord::create([
        'uuid' => Str::uuid()->toString(),
        'method' => 'GET',
        'uri' => '/api/users',
        'query_count' => 10,
        'slow_query_count' => 2,
        'duration_ms' => 200.0,
        'memory_mb' => 20.0,
        'grade' => 'A',
        'has_n_plus_one' => true,
        'has_slow_queries' => true,
        'ip_address' => '127.0.0.1',
        'created_at' => now(),
    ]);

    PerformanceRecord::create([
        'uuid' => Str::uuid()->toString(),
        'method' => 'GET',
        'uri' => '/api/posts',
        'query_count' => 20,
        'slow_query_count' => 0,
        'duration_ms' => 400.0,
        'memory_mb' => 40.0,
        'grade' => 'B',
        'has_n_plus_one' => false,
        'has_slow_queries' => false,
        'ip_address' => '127.0.0.1',
        'created_at' => now(),
    ]);

    $stats = $this->manager->getStats('24h');

    expect($stats['total_requests'])->toBe(2);
    expect($stats['avg_duration_ms'])->toBe(300.0);
    expect($stats['avg_queries'])->toBe(15.0);
    expect($stats['n_plus_one_count'])->toBe(1);
    expect($stats['slow_query_count'])->toBe(1);
    expect($stats['avg_memory_mb'])->toBe(30.0);
});

it('returns grade distribution from database', function () {
    PerformanceRecord::create([
        'uuid' => Str::uuid()->toString(),
        'method' => 'GET',
        'uri' => '/fast',
        'query_count' => 1,
        'slow_query_count' => 0,
        'duration_ms' => 50.0,
        'memory_mb' => 5.0,
        'grade' => 'A',
        'has_n_plus_one' => false,
        'has_slow_queries' => false,
        'ip_address' => '127.0.0.1',
        'created_at' => now(),
    ]);

    PerformanceRecord::create([
        'uuid' => Str::uuid()->toString(),
        'method' => 'GET',
        'uri' => '/slow',
        'query_count' => 50,
        'slow_query_count' => 5,
        'duration_ms' => 5000.0,
        'memory_mb' => 100.0,
        'grade' => 'F',
        'has_n_plus_one' => true,
        'has_slow_queries' => true,
        'ip_address' => '127.0.0.1',
        'created_at' => now(),
    ]);

    $dist = $this->manager->getGradeDistribution('24h');

    expect($dist['A'])->toBe(1);
    expect($dist['B'])->toBe(0);
    expect($dist['C'])->toBe(0);
    expect($dist['D'])->toBe(0);
    expect($dist['F'])->toBe(1);
});

it('respects period filtering', function () {
    PerformanceRecord::create([
        'uuid' => Str::uuid()->toString(),
        'method' => 'GET',
        'uri' => '/old',
        'query_count' => 1,
        'slow_query_count' => 0,
        'duration_ms' => 100.0,
        'memory_mb' => 5.0,
        'grade' => 'A',
        'has_n_plus_one' => false,
        'has_slow_queries' => false,
        'ip_address' => '127.0.0.1',
        'created_at' => now()->subDays(3),
    ]);

    $stats1h = $this->manager->getStats('1h');
    expect($stats1h['total_requests'])->toBe(0);

    $stats7d = $this->manager->getStats('7d');
    expect($stats7d['total_requests'])->toBe(1);
});

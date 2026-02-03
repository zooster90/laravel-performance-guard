<?php

declare(strict_types=1);

use Zufarmarwah\PerformanceGuard\Jobs\StorePerformanceRecordJob;
use Zufarmarwah\PerformanceGuard\Models\PerformanceQuery;
use Zufarmarwah\PerformanceGuard\Models\PerformanceRecord;

beforeEach(function () {
    $this->artisan('migrate');
});

it('stores a performance record', function () {
    $recordData = [
        'method' => 'GET',
        'uri' => '/api/users',
        'controller' => 'UserController',
        'action' => 'index',
        'query_count' => 3,
        'slow_query_count' => 1,
        'duration_ms' => 250.5,
        'memory_mb' => 15.0,
        'grade' => 'B',
        'has_n_plus_one' => false,
        'has_slow_queries' => true,
        'status_code' => 200,
        'user_id' => null,
        'ip_address' => '127.0.0.1',
    ];

    $queryData = [];

    $job = new StorePerformanceRecordJob($recordData, $queryData);
    $job->handle();

    expect(PerformanceRecord::count())->toBe(1);

    $record = PerformanceRecord::first();

    expect($record->method)->toBe('GET');
    expect($record->uri)->toBe('/api/users');
    expect($record->grade)->toBe('B');
    expect($record->uuid)->not->toBeNull();
});

it('stores queries with the record', function () {
    $recordData = [
        'method' => 'GET',
        'uri' => '/api/posts',
        'controller' => null,
        'action' => null,
        'query_count' => 2,
        'slow_query_count' => 1,
        'duration_ms' => 500.0,
        'memory_mb' => 20.0,
        'grade' => 'C',
        'has_n_plus_one' => true,
        'has_slow_queries' => true,
        'status_code' => 200,
        'user_id' => null,
        'ip_address' => '127.0.0.1',
    ];

    $queryData = [
        [
            'sql' => 'select * from posts',
            'normalized' => 'select * from posts',
            'duration' => 50.0,
            'is_slow' => false,
            'is_duplicate' => false,
            'file' => 'app/Http/Controllers/PostController.php',
            'line' => 42,
        ],
        [
            'sql' => 'select * from comments where post_id = 1',
            'normalized' => 'select * from comments where post_id = ?',
            'duration' => 350.0,
            'is_slow' => true,
            'is_duplicate' => true,
            'file' => 'app/Http/Controllers/PostController.php',
            'line' => 45,
        ],
    ];

    $job = new StorePerformanceRecordJob($recordData, $queryData);
    $job->handle();

    expect(PerformanceRecord::count())->toBe(1);
    expect(PerformanceQuery::count())->toBe(2);

    $record = PerformanceRecord::first();
    $queries = $record->queries;

    expect($queries)->toHaveCount(2);
    expect($queries[0]->sql)->toBe('select * from posts');
    expect($queries[1]->is_slow)->toBeTrue();
    expect($queries[1]->is_duplicate)->toBeTrue();
});

it('handles bulk insert with chunking', function () {
    $recordData = [
        'method' => 'GET',
        'uri' => '/heavy',
        'controller' => null,
        'action' => null,
        'query_count' => 150,
        'slow_query_count' => 0,
        'duration_ms' => 1000.0,
        'memory_mb' => 50.0,
        'grade' => 'C',
        'has_n_plus_one' => false,
        'has_slow_queries' => false,
        'status_code' => 200,
        'user_id' => null,
        'ip_address' => '127.0.0.1',
    ];

    $queryData = [];

    for ($i = 0; $i < 150; $i++) {
        $queryData[] = [
            'sql' => "select * from table_{$i}",
            'normalized' => "select * from table_?",
            'duration' => 5.0,
            'is_slow' => false,
            'is_duplicate' => false,
            'file' => null,
            'line' => null,
        ];
    }

    $job = new StorePerformanceRecordJob($recordData, $queryData);
    $job->handle();

    expect(PerformanceQuery::count())->toBe(150);
});

<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Zufarmarwah\PerformanceGuard\Models\PerformanceRecord;

beforeEach(function () {
    $this->artisan('migrate');

    config([
        'performance-guard.dashboard.auth' => false,
        'performance-guard.dashboard.cache_ttl' => 0,
    ]);
});

function createRecord(array $overrides = []): PerformanceRecord
{
    return PerformanceRecord::create(array_merge([
        'uuid' => Str::uuid()->toString(),
        'method' => 'GET',
        'uri' => '/api/users',
        'query_count' => 5,
        'slow_query_count' => 0,
        'duration_ms' => 150.0,
        'memory_mb' => 10.0,
        'grade' => 'A',
        'has_n_plus_one' => false,
        'has_slow_queries' => false,
        'ip_address' => '127.0.0.1',
        'created_at' => now(),
    ], $overrides));
}

it('renders dashboard index', function () {
    createRecord();

    $response = $this->get('/performance-guard');

    $response->assertStatus(200);
});

it('returns api stats as json', function () {
    createRecord();
    createRecord(['grade' => 'B', 'duration_ms' => 400.0]);

    $response = $this->getJson('/performance-guard/api');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'data' => [
                'stats' => [
                    'total_requests',
                    'avg_duration_ms',
                    'avg_queries',
                    'n_plus_one_count',
                    'slow_query_count',
                    'avg_memory_mb',
                ],
                'grade_distribution',
                'records',
            ],
        ]);

    $data = $response->json('data');

    expect($data['stats']['total_requests'])->toBe(2);
});

it('returns single record by uuid', function () {
    $record = createRecord();

    $response = $this->getJson('/performance-guard/api/' . $record->uuid);

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.uuid', $record->uuid);
});

it('returns 404 for unknown uuid', function () {
    $response = $this->getJson('/performance-guard/api/00000000-0000-0000-0000-000000000000');

    $response->assertStatus(404);
});

it('returns n-plus-one records as json', function () {
    createRecord(['has_n_plus_one' => true]);
    createRecord(['has_n_plus_one' => false]);

    $response = $this->getJson('/performance-guard/n-plus-one');

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('meta.total', 1);
});

it('returns slow query records as json', function () {
    createRecord(['has_slow_queries' => true]);
    createRecord(['has_slow_queries' => false]);

    $response = $this->getJson('/performance-guard/slow-queries');

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('meta.total', 1);
});

it('returns routes aggregation as json', function () {
    createRecord(['method' => 'GET', 'uri' => '/api/users', 'duration_ms' => 100.0]);
    createRecord(['method' => 'GET', 'uri' => '/api/users', 'duration_ms' => 200.0]);
    createRecord(['method' => 'POST', 'uri' => '/api/posts', 'duration_ms' => 500.0]);

    $response = $this->getJson('/performance-guard/routes');

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('meta.total', 2);
});

it('filters by period parameter', function () {
    createRecord();

    $response = $this->getJson('/performance-guard/api?period=1h');

    $response->assertStatus(200);

    $response = $this->getJson('/performance-guard/api?period=7d');

    $response->assertStatus(200);
});

it('returns empty stats when no data', function () {
    $response = $this->getJson('/performance-guard/api');

    $response->assertStatus(200);

    $stats = $response->json('data.stats');

    expect($stats['total_requests'])->toBe(0);
    expect((float) $stats['avg_duration_ms'])->toBe(0.0);
});

it('renders request detail page', function () {
    $record = createRecord();

    $response = $this->get('/performance-guard/request/' . $record->uuid);

    $response->assertStatus(200);
    $response->assertSee($record->uri);
    $response->assertSee('All Queries');
});

it('shows duplicate query groups on detail page', function () {
    $record = createRecord(['has_n_plus_one' => true]);

    \Zufarmarwah\PerformanceGuard\Models\PerformanceQuery::insert([
        [
            'performance_record_id' => $record->id,
            'sql' => 'select * from posts where user_id = 1',
            'normalized_sql' => 'select * from posts where user_id = ?',
            'duration_ms' => 1.5,
            'is_slow' => false,
            'is_duplicate' => true,
            'file' => 'app/Http/Controllers/UserController.php',
            'line' => 25,
            'created_at' => now(),
        ],
        [
            'performance_record_id' => $record->id,
            'sql' => 'select * from posts where user_id = 2',
            'normalized_sql' => 'select * from posts where user_id = ?',
            'duration_ms' => 1.2,
            'is_slow' => false,
            'is_duplicate' => true,
            'file' => 'app/Http/Controllers/UserController.php',
            'line' => 25,
            'created_at' => now(),
        ],
    ]);

    $response = $this->get('/performance-guard/request/' . $record->uuid);

    $response->assertStatus(200);
    $response->assertSee('Duplicate Query Patterns');
    $response->assertSee('2x');
    $response->assertSee('eager load');
});

it('returns 404 for unknown request detail uuid', function () {
    $response = $this->get('/performance-guard/request/00000000-0000-0000-0000-000000000000');

    $response->assertStatus(404);
});

it('renders n-plus-one page as html', function () {
    createRecord(['has_n_plus_one' => true]);

    $response = $this->get('/performance-guard/n-plus-one');

    $response->assertStatus(200);
    $response->assertSee('N+1 Query Issues');
});

it('renders slow-queries page as html', function () {
    createRecord(['has_slow_queries' => true]);

    $response = $this->get('/performance-guard/slow-queries');

    $response->assertStatus(200);
    $response->assertSee('Slow Queries');
});

it('renders routes page as html', function () {
    createRecord();

    $response = $this->get('/performance-guard/routes');

    $response->assertStatus(200);
    $response->assertSee('Route Performance');
    $response->assertSee('Export CSV');
});

it('exports routes as csv', function () {
    createRecord(['method' => 'GET', 'uri' => '/api/users', 'duration_ms' => 100.0]);
    createRecord(['method' => 'GET', 'uri' => '/api/users', 'duration_ms' => 200.0]);

    $response = $this->get('/performance-guard/routes/export?period=24h');

    $response->assertStatus(200);
    expect($response->headers->get('content-type'))->toContain('text/csv');
    $response->assertHeader('content-disposition');

    $content = $response->streamedContent();

    expect($content)->toContain('Method');
    expect($content)->toContain('/api/users');
});

it('includes controller column in routes json', function () {
    createRecord([
        'controller' => 'App\\Http\\Controllers\\UserController',
        'action' => 'index',
    ]);

    $response = $this->getJson('/performance-guard/routes');

    $response->assertStatus(200);

    $data = $response->json('data');

    expect($data)->toHaveCount(1);
});

it('includes impact score in routes json', function () {
    createRecord(['method' => 'GET', 'uri' => '/api/users', 'duration_ms' => 100.0]);
    createRecord(['method' => 'GET', 'uri' => '/api/users', 'duration_ms' => 200.0]);

    $response = $this->getJson('/performance-guard/routes');

    $response->assertStatus(200);

    $route = $response->json('data.0');

    expect((int) $route['impact_score'])->toBeGreaterThan(0);
});

it('auto-refreshes the dashboard page', function () {
    createRecord();

    $response = $this->get('/performance-guard');

    $response->assertStatus(200);
    $response->assertSee('auto-refresh', false);
});

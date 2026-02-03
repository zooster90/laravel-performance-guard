<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Queue;
use Zufarmarwah\PerformanceGuard\Jobs\StorePerformanceRecordJob;
use Zufarmarwah\PerformanceGuard\Listeners\QueryListener;
use Zufarmarwah\PerformanceGuard\Middleware\PerformanceMonitoringMiddleware;

beforeEach(function () {
    $this->artisan('migrate');

    config([
        'performance-guard.enabled' => true,
        'performance-guard.sampling_rate' => 1.0,
        'performance-guard.storage.async' => true,
    ]);
});

function callMiddleware(Request $request): Response
{
    $middleware = app(PerformanceMonitoringMiddleware::class);

    return $middleware->handle($request, function () {
        return new Response('ok');
    });
}

it('dispatches storage job for monitored requests', function () {
    Queue::fake();

    $request = Request::create('/api/users', 'GET');

    $response = callMiddleware($request);

    expect($response->getStatusCode())->toBe(200);

    Queue::assertPushed(StorePerformanceRecordJob::class);
});

it('skips monitoring when disabled', function () {
    Queue::fake();
    config(['performance-guard.enabled' => false]);

    $request = Request::create('/api/users', 'GET');

    $response = callMiddleware($request);

    expect($response->getStatusCode())->toBe(200);

    Queue::assertNotPushed(StorePerformanceRecordJob::class);
});

it('skips OPTIONS requests', function () {
    Queue::fake();

    $request = Request::create('/api/users', 'OPTIONS');

    $response = callMiddleware($request);

    expect($response->getStatusCode())->toBe(200);

    Queue::assertNotPushed(StorePerformanceRecordJob::class);
});

it('skips HEAD requests', function () {
    Queue::fake();

    $request = Request::create('/api/users', 'HEAD');

    $response = callMiddleware($request);

    expect($response->getStatusCode())->toBe(200);

    Queue::assertNotPushed(StorePerformanceRecordJob::class);
});

it('skips ignored routes', function () {
    Queue::fake();
    config(['performance-guard.ignored_routes' => ['health']]);

    $request = Request::create('/health', 'GET');

    $response = callMiddleware($request);

    Queue::assertNotPushed(StorePerformanceRecordJob::class);
});

it('skips privacy excluded paths', function () {
    Queue::fake();
    config(['performance-guard.privacy.exclude_paths' => ['_debugbar/*']]);

    $request = Request::create('/_debugbar/open', 'GET');

    $response = callMiddleware($request);

    Queue::assertNotPushed(StorePerformanceRecordJob::class);
});

it('resets query listener after request (Octane safe)', function () {
    Queue::fake();

    $listener = app(QueryListener::class);

    $request = Request::create('/api/users', 'GET');

    callMiddleware($request);

    expect($listener->getQueries())->toBeEmpty();
    expect($listener->isListening())->toBeFalse();
});

it('resets query listener on exception (Octane safe)', function () {
    Queue::fake();

    $listener = app(QueryListener::class);
    $middleware = app(PerformanceMonitoringMiddleware::class);

    $request = Request::create('/api/users', 'GET');

    try {
        $middleware->handle($request, function () {
            throw new RuntimeException('test error');
        });
    } catch (RuntimeException) {
        // expected
    }

    expect($listener->getQueries())->toBeEmpty();
    expect($listener->isListening())->toBeFalse();
});

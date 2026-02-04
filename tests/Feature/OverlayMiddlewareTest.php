<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Zufarmarwah\PerformanceGuard\Middleware\PerformanceOverlayMiddleware;

beforeEach(function () {
    $this->artisan('migrate');

    config([
        'performance-guard.enabled' => true,
        'performance-guard.overlay.enabled' => true,
    ]);
});

function callOverlayMiddleware(Request $request, string $content = '<html><body>Hello</body></html>', string $contentType = 'text/html'): Response
{
    $middleware = app(PerformanceOverlayMiddleware::class);

    return $middleware->handle($request, function () use ($content, $contentType) {
        return new Response($content, 200, ['Content-Type' => $contentType]);
    });
}

it('injects overlay into html responses when enabled', function () {
    $request = Request::create('/dashboard', 'GET');

    $response = callOverlayMiddleware($request);

    expect($response->getContent())->toContain('pg-overlay');
    expect($response->getContent())->toContain('Performance Guard');
});

it('does not inject overlay when disabled', function () {
    config(['performance-guard.overlay.enabled' => false]);

    $request = Request::create('/dashboard', 'GET');
    $middleware = app(PerformanceOverlayMiddleware::class);

    $response = $middleware->handle($request, function () {
        return new Response('<html><body>Hello</body></html>', 200, ['Content-Type' => 'text/html']);
    });

    expect($response->getContent())->not->toContain('pg-overlay');
});

it('does not inject overlay into json responses', function () {
    $request = Request::create('/api/data', 'GET');

    $response = callOverlayMiddleware($request, '{"data": true}', 'application/json');

    expect($response->getContent())->not->toContain('pg-overlay');
});

it('shows grade badge in overlay pill', function () {
    $request = Request::create('/dashboard', 'GET');

    $response = callOverlayMiddleware($request);
    $content = $response->getContent();

    expect($content)->toContain('pg-pill');
    expect($content)->toContain('0q');
});

it('includes web vitals script when enabled', function () {
    config(['performance-guard.overlay.web_vitals' => true]);

    $request = Request::create('/dashboard', 'GET');

    $response = callOverlayMiddleware($request);

    expect($response->getContent())->toContain('pg-vitals');
    expect($response->getContent())->toContain('PerformanceObserver');
});

it('excludes web vitals script when disabled', function () {
    config(['performance-guard.overlay.web_vitals' => false]);

    $request = Request::create('/dashboard', 'GET');

    $response = callOverlayMiddleware($request);

    expect($response->getContent())->not->toContain('pg-vitals');
});

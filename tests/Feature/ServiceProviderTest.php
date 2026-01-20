<?php

declare(strict_types=1);

use Zufarmarwah\PerformanceGuard\Analyzers\NPlusOneAnalyzer;
use Zufarmarwah\PerformanceGuard\Analyzers\PerformanceScorer;
use Zufarmarwah\PerformanceGuard\Analyzers\SlowQueryAnalyzer;
use Zufarmarwah\PerformanceGuard\Listeners\QueryListener;
use Zufarmarwah\PerformanceGuard\PerformanceGuardManager;

it('registers singletons in the container', function () {
    expect(app(QueryListener::class))->toBeInstanceOf(QueryListener::class);
    expect(app(NPlusOneAnalyzer::class))->toBeInstanceOf(NPlusOneAnalyzer::class);
    expect(app(SlowQueryAnalyzer::class))->toBeInstanceOf(SlowQueryAnalyzer::class);
    expect(app(PerformanceScorer::class))->toBeInstanceOf(PerformanceScorer::class);
    expect(app(PerformanceGuardManager::class))->toBeInstanceOf(PerformanceGuardManager::class);
});

it('returns same instance for singletons', function () {
    $first = app(QueryListener::class);
    $second = app(QueryListener::class);

    expect($first)->toBe($second);
});

it('merges config', function () {
    expect(config('performance-guard.enabled'))->toBeTrue();
    expect(config('performance-guard.sampling_rate'))->toBe(1.0);
    expect(config('performance-guard.thresholds.slow_query_ms'))->toBe(300);
    expect(config('performance-guard.thresholds.n_plus_one'))->toBe(10);
});

it('registers middleware alias', function () {
    $router = app('router');
    $middleware = $router->getMiddleware();

    expect($middleware)->toHaveKey('performance-guard');
});

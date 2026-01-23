<?php

declare(strict_types=1);

namespace Zufarmarwah\PerformanceGuard\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Zufarmarwah\PerformanceGuard\Analyzers\NPlusOneAnalyzer;
use Zufarmarwah\PerformanceGuard\Analyzers\PerformanceScorer;
use Zufarmarwah\PerformanceGuard\Jobs\StorePerformanceRecordJob;
use Zufarmarwah\PerformanceGuard\Listeners\QueryListener;
use Zufarmarwah\PerformanceGuard\Notifications\NotificationDispatcher;

class PerformanceMonitoringMiddleware
{
    public function __construct(
        private readonly QueryListener $queryListener,
        private readonly NPlusOneAnalyzer $nPlusOneAnalyzer,
        private readonly PerformanceScorer $scorer,
        private readonly NotificationDispatcher $notificationDispatcher,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->shouldMonitor($request)) {
            return $next($request);
        }

        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        $this->queryListener->start();

        $response = $next($request);

        $this->queryListener->stop();

        try {
            $durationMs = (microtime(true) - $startTime) * 1000;
            $memoryMb = (memory_get_usage(true) - $startMemory) / 1024 / 1024;

            $this->recordPerformance($request, $response, $durationMs, $memoryMb);
        } catch (\Throwable $e) {
            Log::warning('Performance Guard: failed to record metrics', [
                'error' => $e->getMessage(),
            ]);
        }

        return $response;
    }

    private function shouldMonitor(Request $request): bool
    {
        if (! config('performance-guard.enabled', true)) {
            return false;
        }

        $samplingRate = (float) config('performance-guard.sampling_rate', 1.0);

        if ($samplingRate < 1.0 && (mt_rand() / mt_getrandmax()) > $samplingRate) {
            return false;
        }

        $excludedPaths = config('performance-guard.ignored_routes', []);

        foreach ($excludedPaths as $pattern) {
            if ($request->is($pattern)) {
                return false;
            }
        }

        $privacyExclusions = config('performance-guard.privacy.exclude_paths', []);

        foreach ($privacyExclusions as $pattern) {
            if ($request->is($pattern)) {
                return false;
            }
        }

        return true;
    }

    private function recordPerformance(Request $request, Response $response, float $durationMs, float $memoryMb): void
    {
        $queries = $this->queryListener->getQueries();
        $slowThreshold = (float) config('performance-guard.thresholds.slow_query_ms', 300);
        $nPlusOneThreshold = (int) config('performance-guard.thresholds.n_plus_one', 10);

        $analysis = $this->nPlusOneAnalyzer->analyze($queries, $nPlusOneThreshold);
        $slowQueries = $this->queryListener->getSlowQueries($slowThreshold);
        $grade = $this->scorer->grade($durationMs);

        $route = $request->route();
        $controller = null;
        $action = null;

        if ($route !== null) {
            $routeAction = $route->getAction();

            if (isset($routeAction['controller'])) {
                $parts = explode('@', $routeAction['controller']);
                $controller = $parts[0] ?? null;
                $action = $parts[1] ?? null;
            }
        }

        $recordData = [
            'method' => $request->method(),
            'uri' => $request->path(),
            'controller' => $controller,
            'action' => $action,
            'query_count' => count($queries),
            'slow_query_count' => count($slowQueries),
            'duration_ms' => round($durationMs, 2),
            'memory_mb' => round($memoryMb, 2),
            'grade' => $grade,
            'has_n_plus_one' => $analysis['hasNPlusOne'],
            'has_slow_queries' => count($slowQueries) > 0,
            'status_code' => $response->getStatusCode(),
            'user_id' => $request->user()?->getAuthIdentifier(),
            'ip_address' => config('performance-guard.privacy.store_ip', true)
                ? $request->ip()
                : null,
        ];

        $duplicateIndices = [];

        foreach ($analysis['duplicates'] as $group) {
            foreach ($group['indices'] as $idx) {
                $duplicateIndices[$idx] = true;
            }
        }

        $queryData = [];

        foreach ($queries as $index => $query) {
            $queryData[] = [
                'sql' => $this->redactSensitiveData($query['sql']),
                'normalized' => $query['normalized'],
                'duration' => $query['duration'],
                'is_slow' => $query['duration'] >= $slowThreshold,
                'is_duplicate' => isset($duplicateIndices[$index]),
                'file' => $this->stripBasePath($query['file']),
                'line' => $query['line'],
            ];
        }

        $async = config('performance-guard.storage.async', true);

        if ($async) {
            StorePerformanceRecordJob::dispatch($recordData, $queryData);
        } else {
            StorePerformanceRecordJob::dispatchSync($recordData, $queryData);
        }

        $this->notificationDispatcher->dispatch($recordData, $analysis, $slowQueries, $memoryMb);
    }

    private function redactSensitiveData(string $sql): string
    {
        if (! config('performance-guard.privacy.redact_bindings', true)) {
            return $sql;
        }

        $patterns = config('performance-guard.privacy.redact_patterns', []);

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $sql)) {
                $redacted = preg_replace("/'[^']*'/", "'[REDACTED]'", $sql) ?? $sql;
                $redacted = preg_replace('/\b\d{4,}\b/', '[REDACTED]', $redacted) ?? $redacted;

                return $redacted;
            }
        }

        return $sql;
    }

    private function stripBasePath(?string $file): ?string
    {
        if ($file === null) {
            return null;
        }

        $basePath = base_path() . DIRECTORY_SEPARATOR;

        if (str_starts_with($file, $basePath)) {
            return substr($file, strlen($basePath));
        }

        return $file;
    }
}

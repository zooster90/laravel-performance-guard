<?php

declare(strict_types=1);

namespace Zufarmarwah\PerformanceGuard\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Zufarmarwah\PerformanceGuard\Models\PerformanceRecord;

class DashboardController extends Controller
{
    private const MAX_PAGE = 1000;

    public function index(Request $request)
    {
        $period = $request->get('period', '24h');
        $since = $this->resolveSince($period);
        $previousSince = $this->resolvePreviousSince($period);
        $cacheKey = 'performance-guard:dashboard:' . $period;
        $cacheTtl = (int) config('performance-guard.dashboard.cache_ttl', 60);

        $cached = Cache::remember($cacheKey, $cacheTtl, function () use ($since, $previousSince) {
            return [
                'stats' => $this->buildStats($since),
                'previousStats' => $this->buildStats($previousSince, $since),
                'gradeDistribution' => $this->getGradeDistribution($since),
            ];
        });

        $recentRecords = PerformanceRecord::query()
            ->where('created_at', '>=', $since)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return view('performance-guard::dashboard.index', [
            'stats' => $cached['stats'],
            'previousStats' => $cached['previousStats'],
            'gradeDistribution' => $cached['gradeDistribution'],
            'records' => $recentRecords,
            'period' => $period,
        ]);
    }

    public function api(Request $request): JsonResponse
    {
        $period = $request->get('period', '24h');
        $since = $this->resolveSince($period);
        $cacheKey = 'performance-guard:api:' . $period;
        $cacheTtl = (int) config('performance-guard.dashboard.cache_ttl', 60);

        $cached = Cache::remember($cacheKey, $cacheTtl, function () use ($since) {
            return [
                'stats' => $this->buildStats($since),
                'grade_distribution' => $this->getGradeDistribution($since),
            ];
        });

        return new JsonResponse([
            'success' => true,
            'data' => [
                'stats' => $cached['stats'],
                'grade_distribution' => $cached['grade_distribution'],
                'records' => PerformanceRecord::query()
                    ->where('created_at', '>=', $since)
                    ->orderByDesc('created_at')
                    ->limit(50)
                    ->get(),
            ],
        ]);
    }

    public function show(string $uuid): JsonResponse
    {
        $record = PerformanceRecord::where('uuid', $uuid)
            ->with('queries')
            ->firstOrFail();

        return new JsonResponse([
            'success' => true,
            'data' => $record,
        ]);
    }

    public function nPlusOne(Request $request)
    {
        $page = min(max(1, (int) $request->get('page', 1)), self::MAX_PAGE);
        $perPage = 50;

        $records = PerformanceRecord::query()
            ->withNPlusOne()
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);

        if ($request->wantsJson()) {
            return new JsonResponse([
                'success' => true,
                'data' => $records->items(),
                'meta' => [
                    'total' => $records->total(),
                    'page' => $records->currentPage(),
                    'per_page' => $records->perPage(),
                    'last_page' => $records->lastPage(),
                ],
            ]);
        }

        return view('performance-guard::dashboard.n-plus-one', [
            'records' => $records,
        ]);
    }

    public function slowQueries(Request $request)
    {
        $page = min(max(1, (int) $request->get('page', 1)), self::MAX_PAGE);
        $perPage = 50;

        $records = PerformanceRecord::query()
            ->slow()
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);

        if ($request->wantsJson()) {
            return new JsonResponse([
                'success' => true,
                'data' => $records->items(),
                'meta' => [
                    'total' => $records->total(),
                    'page' => $records->currentPage(),
                    'per_page' => $records->perPage(),
                    'last_page' => $records->lastPage(),
                ],
            ]);
        }

        return view('performance-guard::dashboard.slow-queries', [
            'records' => $records,
        ]);
    }

    public function routes(Request $request)
    {
        $period = $request->get('period', '24h');
        $since = $this->resolveSince($period);
        $page = min(max(1, (int) $request->get('page', 1)), self::MAX_PAGE);
        $perPage = 50;

        $routes = $this->getRouteStats($since, $perPage, $page);

        if ($request->wantsJson()) {
            return new JsonResponse([
                'success' => true,
                'data' => $routes->items(),
                'meta' => [
                    'total' => $routes->total(),
                    'page' => $routes->currentPage(),
                    'per_page' => $routes->perPage(),
                    'last_page' => $routes->lastPage(),
                ],
            ]);
        }

        return view('performance-guard::dashboard.routes', [
            'routes' => $routes,
            'period' => $period,
        ]);
    }

    private function resolveSince(string $period): Carbon
    {
        return match ($period) {
            '1h' => now()->subHour(),
            '24h' => now()->subDay(),
            '7d' => now()->subWeek(),
            '30d' => now()->subMonth(),
            default => now()->subDay(),
        };
    }

    private function resolvePreviousSince(string $period): Carbon
    {
        return match ($period) {
            '1h' => now()->subHours(2),
            '24h' => now()->subDays(2),
            '7d' => now()->subWeeks(2),
            '30d' => now()->subMonths(2),
            default => now()->subDays(2),
        };
    }

    /**
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    private function getRouteStats(Carbon $since, int $perPage, int $page)
    {
        return PerformanceRecord::query()
            ->where('created_at', '>=', $since)
            ->select(
                'method',
                'uri',
                DB::raw('COUNT(*) as request_count'),
                DB::raw('ROUND(AVG(duration_ms), 0) as avg_duration'),
                DB::raw('ROUND(AVG(query_count), 0) as avg_queries'),
                DB::raw('ROUND(AVG(memory_mb), 1) as avg_memory'),
                DB::raw('MAX(grade) as worst_grade'),
                DB::raw('SUM(CASE WHEN has_n_plus_one THEN 1 ELSE 0 END) as n_plus_one_hits'),
                DB::raw('SUM(CASE WHEN has_slow_queries THEN 1 ELSE 0 END) as slow_query_hits')
            )
            ->groupBy('method', 'uri')
            ->orderByDesc(DB::raw('AVG(duration_ms)'))
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildStats(Carbon $since, ?Carbon $until = null): array
    {
        $query = PerformanceRecord::query()
            ->where('created_at', '>=', $since);

        if ($until !== null) {
            $query->where('created_at', '<', $until);
        }

        $result = $query
            ->selectRaw('COUNT(*) as total_requests')
            ->selectRaw('COALESCE(AVG(duration_ms), 0) as avg_duration_ms')
            ->selectRaw('COALESCE(AVG(query_count), 0) as avg_queries')
            ->selectRaw('SUM(CASE WHEN has_n_plus_one THEN 1 ELSE 0 END) as n_plus_one_count')
            ->selectRaw('SUM(CASE WHEN has_slow_queries THEN 1 ELSE 0 END) as slow_query_count')
            ->selectRaw('COALESCE(AVG(memory_mb), 0) as avg_memory_mb')
            ->first();

        return [
            'total_requests' => (int) ($result->total_requests ?? 0),
            'avg_duration_ms' => round((float) ($result->avg_duration_ms ?? 0), 2),
            'avg_queries' => round((float) ($result->avg_queries ?? 0), 1),
            'n_plus_one_count' => (int) ($result->n_plus_one_count ?? 0),
            'slow_query_count' => (int) ($result->slow_query_count ?? 0),
            'avg_memory_mb' => round((float) ($result->avg_memory_mb ?? 0), 2),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function getGradeDistribution(Carbon $since): array
    {
        $distribution = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'F' => 0];

        $results = PerformanceRecord::query()
            ->where('created_at', '>=', $since)
            ->select('grade', DB::raw('COUNT(*) as count'))
            ->groupBy('grade')
            ->pluck('count', 'grade');

        foreach ($results as $grade => $count) {
            if (isset($distribution[$grade])) {
                $distribution[$grade] = (int) $count;
            }
        }

        return $distribution;
    }
}

<?php

declare(strict_types=1);

namespace Zufarmarwah\PerformanceGuard\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Zufarmarwah\PerformanceGuard\Models\PerformanceRecord;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $period = $request->get('period', '24h');
        $records = $this->getRecordsForPeriod($period);

        $stats = $this->buildStats($records);
        $gradeDistribution = $this->getGradeDistribution($records);
        $recentRecords = PerformanceRecord::query()
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return view('performance-guard::dashboard.index', [
            'stats' => $stats,
            'gradeDistribution' => $gradeDistribution,
            'records' => $recentRecords,
            'period' => $period,
        ]);
    }

    public function api(Request $request): JsonResponse
    {
        $period = $request->get('period', '24h');
        $records = $this->getRecordsForPeriod($period);

        return new JsonResponse([
            'success' => true,
            'data' => [
                'stats' => $this->buildStats($records),
                'grade_distribution' => $this->getGradeDistribution($records),
                'records' => PerformanceRecord::query()
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
        $records = PerformanceRecord::query()
            ->withNPlusOne()
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        if ($request->wantsJson()) {
            return new JsonResponse([
                'success' => true,
                'data' => $records,
            ]);
        }

        return view('performance-guard::dashboard.n-plus-one', [
            'records' => $records,
        ]);
    }

    public function slowQueries(Request $request)
    {
        $records = PerformanceRecord::query()
            ->slow()
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        if ($request->wantsJson()) {
            return new JsonResponse([
                'success' => true,
                'data' => $records,
            ]);
        }

        return view('performance-guard::dashboard.slow-queries', [
            'records' => $records,
        ]);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, PerformanceRecord>
     */
    private function getRecordsForPeriod(string $period)
    {
        $query = PerformanceRecord::query();

        $since = match ($period) {
            '1h' => now()->subHour(),
            '24h' => now()->subDay(),
            '7d' => now()->subWeek(),
            '30d' => now()->subMonth(),
            default => now()->subDay(),
        };

        return $query->where('created_at', '>=', $since)->get();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, PerformanceRecord>  $records
     * @return array<string, mixed>
     */
    private function buildStats($records): array
    {
        if ($records->isEmpty()) {
            return [
                'total_requests' => 0,
                'avg_duration_ms' => 0,
                'avg_queries' => 0,
                'n_plus_one_count' => 0,
                'slow_query_count' => 0,
                'avg_memory_mb' => 0,
            ];
        }

        return [
            'total_requests' => $records->count(),
            'avg_duration_ms' => round($records->avg('duration_ms'), 2),
            'avg_queries' => round($records->avg('query_count'), 1),
            'n_plus_one_count' => $records->where('has_n_plus_one', true)->count(),
            'slow_query_count' => $records->where('has_slow_queries', true)->count(),
            'avg_memory_mb' => round($records->avg('memory_mb'), 2),
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, PerformanceRecord>  $records
     * @return array<string, int>
     */
    private function getGradeDistribution($records): array
    {
        $distribution = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'F' => 0];

        foreach ($records as $record) {
            $grade = $record->grade;

            if (isset($distribution[$grade])) {
                $distribution[$grade]++;
            }
        }

        return $distribution;
    }
}

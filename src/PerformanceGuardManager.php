<?php

declare(strict_types=1);

namespace Zufarmarwah\PerformanceGuard;

use Illuminate\Support\Facades\DB;
use Zufarmarwah\PerformanceGuard\Models\PerformanceRecord;

class PerformanceGuardManager
{
    public function isEnabled(): bool
    {
        return (bool) config('performance-guard.enabled', true);
    }

    public function enable(): void
    {
        config(['performance-guard.enabled' => true]);
    }

    public function disable(): void
    {
        config(['performance-guard.enabled' => false]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getStats(string $period = '24h'): array
    {
        $since = $this->resolveSince($period);

        $result = PerformanceRecord::query()
            ->where('created_at', '>=', $since)
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
    public function getGradeDistribution(string $period = '24h'): array
    {
        $since = $this->resolveSince($period);

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

    private function resolveSince(string $period): \Carbon\Carbon
    {
        return match ($period) {
            '1h' => now()->subHour(),
            '24h' => now()->subDay(),
            '7d' => now()->subWeek(),
            '30d' => now()->subMonth(),
            default => now()->subDay(),
        };
    }
}

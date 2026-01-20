<?php

declare(strict_types=1);

namespace Zufarmarwah\PerformanceGuard;

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
        $since = match ($period) {
            '1h' => now()->subHour(),
            '24h' => now()->subDay(),
            '7d' => now()->subWeek(),
            '30d' => now()->subMonth(),
            default => now()->subDay(),
        };

        $records = PerformanceRecord::where('created_at', '>=', $since)->get();

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
     * @return array<string, int>
     */
    public function getGradeDistribution(string $period = '24h'): array
    {
        $since = match ($period) {
            '1h' => now()->subHour(),
            '24h' => now()->subDay(),
            '7d' => now()->subWeek(),
            '30d' => now()->subMonth(),
            default => now()->subDay(),
        };

        $records = PerformanceRecord::where('created_at', '>=', $since)->get();

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

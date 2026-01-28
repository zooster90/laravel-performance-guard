<?php

declare(strict_types=1);

namespace Zufarmarwah\PerformanceGuard\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Zufarmarwah\PerformanceGuard\Models\PerformanceRecord;

class StatusCommand extends Command
{
    protected $signature = 'performance-guard:status
                            {--period=24h : Time period (1h, 24h, 7d, 30d)}';

    protected $description = 'Show performance monitoring status summary';

    public function handle(): int
    {
        $period = $this->option('period');
        $since = $this->resolveSince($period);
        $previousSince = $this->resolvePreviousSince($period);

        $current = $this->getStats($since);
        $previous = $this->getStats($previousSince, $since);
        $gradeDistribution = $this->getGradeDistribution($since);
        $slowestRoutes = $this->getSlowestRoutes($since, 5);

        $this->newLine();
        $this->line('<fg=cyan;options=bold>Performance Guard</> - Last ' . $this->periodLabel($period));
        $this->line(str_repeat('─', 50));
        $this->newLine();

        $this->line(sprintf(
            '  Requests:      <fg=white;options=bold>%s</>%s',
            number_format($current['total_requests']),
            $this->trend($current['total_requests'], $previous['total_requests'], false)
        ));

        $this->line(sprintf(
            '  Avg Duration:  <fg=%s;options=bold>%sms</>%s',
            $current['avg_duration_ms'] > 1000 ? 'red' : ($current['avg_duration_ms'] > 500 ? 'yellow' : 'green'),
            number_format($current['avg_duration_ms'], 0),
            $this->trend($current['avg_duration_ms'], $previous['avg_duration_ms'], true)
        ));

        $this->line(sprintf(
            '  Avg Queries:   <fg=%s;options=bold>%s</>%s',
            $current['avg_queries'] > 30 ? 'red' : ($current['avg_queries'] > 15 ? 'yellow' : 'white'),
            number_format($current['avg_queries'], 1),
            $this->trend($current['avg_queries'], $previous['avg_queries'], true)
        ));

        $this->line(sprintf(
            '  Avg Memory:    <fg=white;options=bold>%sMB</>',
            number_format($current['avg_memory_mb'], 1)
        ));

        $this->line(sprintf(
            '  N+1 Issues:    <fg=%s;options=bold>%s</>',
            $current['n_plus_one_count'] > 0 ? 'red' : 'green',
            $current['n_plus_one_count']
        ));

        $this->line(sprintf(
            '  Slow Queries:  <fg=%s;options=bold>%s</>',
            $current['slow_query_count'] > 0 ? 'yellow' : 'green',
            $current['slow_query_count']
        ));

        $this->newLine();
        $this->line('  <fg=white;options=bold>Grade Distribution:</>');

        $total = max(array_sum($gradeDistribution), 1);

        foreach ($gradeDistribution as $grade => $count) {
            $pct = round(($count / $total) * 100);
            $bar = str_repeat('█', (int) ($pct / 2));
            $color = match ($grade) {
                'A' => 'green',
                'B' => 'cyan',
                'C' => 'yellow',
                'D' => 'red',
                'F' => 'red',
                default => 'white',
            };
            $this->line(sprintf(
                '    <fg=%s>%s</> %s <fg=gray>%d (%d%%)</>',
                $color,
                $grade,
                $bar,
                $count,
                $pct
            ));
        }

        if (count($slowestRoutes) > 0) {
            $this->newLine();
            $this->line('  <fg=white;options=bold>Slowest Routes:</>');

            foreach ($slowestRoutes as $route) {
                $this->line(sprintf(
                    '    <fg=%s>%s</> %s %s  <fg=gray>(%s avg, %s queries, %sx)</>',
                    $route->avg_duration > 1000 ? 'red' : ($route->avg_duration > 500 ? 'yellow' : 'green'),
                    str_pad($route->method, 6),
                    str_pad(substr($route->uri, 0, 35), 35),
                    str_pad(number_format($route->avg_duration, 0) . 'ms', 8),
                    number_format($route->avg_queries, 0) . 'q',
                    $route->grade,
                    $route->request_count
                ));
            }
        }

        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function getStats(\DateTimeInterface $since, ?\DateTimeInterface $until = null): array
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
    private function getGradeDistribution(\DateTimeInterface $since): array
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

    /**
     * @return array<int, object>
     */
    private function getSlowestRoutes(\DateTimeInterface $since, int $limit): array
    {
        return PerformanceRecord::query()
            ->where('created_at', '>=', $since)
            ->select(
                'method',
                'uri',
                DB::raw('COUNT(*) as request_count'),
                DB::raw('ROUND(AVG(duration_ms), 0) as avg_duration'),
                DB::raw('ROUND(AVG(query_count), 0) as avg_queries'),
                DB::raw('MAX(grade) as grade')
            )
            ->groupBy('method', 'uri')
            ->orderByDesc(DB::raw('AVG(duration_ms)'))
            ->limit($limit)
            ->get()
            ->all();
    }

    private function trend(float $current, float $previous, bool $lowerIsBetter): string
    {
        if ($previous == 0) {
            return '';
        }

        $change = (($current - $previous) / $previous) * 100;

        if (abs($change) < 1) {
            return ' <fg=gray>(unchanged)</>';
        }

        $direction = $change > 0 ? '↑' : '↓';
        $isGood = $lowerIsBetter ? $change < 0 : $change > 0;

        return sprintf(
            ' <fg=%s>%s%.0f%%</>',
            $isGood ? 'green' : 'red',
            $direction,
            abs($change)
        );
    }

    private function resolveSince(string $period): \DateTimeInterface
    {
        return match ($period) {
            '1h' => now()->subHour(),
            '24h' => now()->subDay(),
            '7d' => now()->subWeek(),
            '30d' => now()->subMonth(),
            default => now()->subDay(),
        };
    }

    private function resolvePreviousSince(string $period): \DateTimeInterface
    {
        return match ($period) {
            '1h' => now()->subHours(2),
            '24h' => now()->subDays(2),
            '7d' => now()->subWeeks(2),
            '30d' => now()->subMonths(2),
            default => now()->subDays(2),
        };
    }

    private function periodLabel(string $period): string
    {
        return match ($period) {
            '1h' => '1 Hour',
            '24h' => '24 Hours',
            '7d' => '7 Days',
            '30d' => '30 Days',
            default => '24 Hours',
        };
    }
}

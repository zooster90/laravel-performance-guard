<?php

declare(strict_types=1);

namespace Zufarmarwah\PerformanceGuard\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Zufarmarwah\PerformanceGuard\Models\PerformanceRecord;

class CheckCommand extends Command
{
    protected $signature = 'performance-guard:check
                            {--period=24h : Time period to check (1h, 24h, 7d, 30d)}
                            {--route= : Filter to a specific route URI}
                            {--max-duration= : Max avg duration in ms (overrides config)}
                            {--max-queries= : Max avg queries per request (overrides config)}
                            {--fail-on-n-plus-one : Fail if any N+1 issues found (overrides config)}';

    protected $description = 'Check performance against budgets for CI/CD pipelines';

    public function handle(): int
    {
        $period = $this->option('period');
        $since = $this->resolveSince($period);
        $routeFilter = $this->option('route');

        $maxDuration = $this->option('max-duration')
            ? (float) $this->option('max-duration')
            : (float) config('performance-guard.ci.max_duration_ms', 500);

        $maxQueries = $this->option('max-queries')
            ? (int) $this->option('max-queries')
            : (int) config('performance-guard.ci.max_queries', 30);

        $failOnNPlusOne = $this->option('fail-on-n-plus-one')
            || (bool) config('performance-guard.ci.fail_on_n_plus_one', true);

        $this->newLine();
        $this->line('<fg=cyan;options=bold>Performance Guard</> - CI Check');
        $this->line(str_repeat('─', 50));
        $this->line(sprintf('  Period: %s | Max Duration: %sms | Max Queries: %s', $period, number_format($maxDuration, 0), $maxQueries));
        $this->newLine();

        $query = PerformanceRecord::query()->where('created_at', '>=', $since);

        if ($routeFilter !== null) {
            $query->where('uri', 'LIKE', '%' . $routeFilter . '%');
        }

        $totalRecords = $query->count();

        if ($totalRecords === 0) {
            $this->warn('  No performance records found for this period.');
            $this->line('  Run your test suite with the middleware active to generate data.');
            $this->newLine();

            return self::SUCCESS;
        }

        $routes = $this->getRouteStats($since, $routeFilter);
        $failures = [];

        foreach ($routes as $route) {
            $routeLabel = $route->method . ' ' . $route->uri;
            $routeFailures = [];

            if ((float) $route->avg_duration > $maxDuration) {
                $routeFailures[] = sprintf(
                    'avg duration %sms exceeds %sms',
                    number_format((float) $route->avg_duration, 0),
                    number_format($maxDuration, 0)
                );
            }

            if ((float) $route->avg_queries > $maxQueries) {
                $routeFailures[] = sprintf(
                    'avg queries %s exceeds %s',
                    number_format((float) $route->avg_queries, 0),
                    $maxQueries
                );
            }

            if ($failOnNPlusOne && (int) $route->n_plus_one_hits > 0) {
                $routeFailures[] = sprintf('%d request(s) with N+1 issues', (int) $route->n_plus_one_hits);
            }

            if (count($routeFailures) > 0) {
                $failures[$routeLabel] = $routeFailures;
                $this->line(sprintf(
                    '  <fg=red>FAIL</> %s %s  <fg=gray>(%sx, %sms avg, %sq avg)</>',
                    str_pad($route->method, 6),
                    str_pad(substr($route->uri, 0, 40), 40),
                    $route->request_count,
                    number_format((float) $route->avg_duration, 0),
                    number_format((float) $route->avg_queries, 0)
                ));

                foreach ($routeFailures as $reason) {
                    $this->line('         <fg=red>→ ' . $reason . '</>');
                }
            } else {
                $this->line(sprintf(
                    '  <fg=green>PASS</> %s %s  <fg=gray>(%sx, %sms avg, %sq avg)</>',
                    str_pad($route->method, 6),
                    str_pad(substr($route->uri, 0, 40), 40),
                    $route->request_count,
                    number_format((float) $route->avg_duration, 0),
                    number_format((float) $route->avg_queries, 0)
                ));
            }
        }

        $this->newLine();
        $this->line(str_repeat('─', 50));

        if (count($failures) > 0) {
            $this->line(sprintf(
                '  <fg=red;options=bold>%d route(s) failed performance budget</>',
                count($failures)
            ));
            $this->newLine();

            return self::FAILURE;
        }

        $this->line(sprintf(
            '  <fg=green;options=bold>All %d route(s) passed performance budget</>',
            count($routes)
        ));
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * @return array<int, object>
     */
    private function getRouteStats(\DateTimeInterface $since, ?string $routeFilter): array
    {
        $query = PerformanceRecord::query()
            ->where('created_at', '>=', $since);

        if ($routeFilter !== null) {
            $query->where('uri', 'LIKE', '%' . $routeFilter . '%');
        }

        return $query
            ->select(
                'method',
                'uri',
                DB::raw('COUNT(*) as request_count'),
                DB::raw('ROUND(AVG(duration_ms), 0) as avg_duration'),
                DB::raw('ROUND(AVG(query_count), 0) as avg_queries'),
                DB::raw('SUM(CASE WHEN has_n_plus_one THEN 1 ELSE 0 END) as n_plus_one_hits'),
                DB::raw('SUM(CASE WHEN has_slow_queries THEN 1 ELSE 0 END) as slow_query_hits'),
                DB::raw('MAX(grade) as worst_grade')
            )
            ->groupBy('method', 'uri')
            ->orderByDesc(DB::raw('COUNT(*) * AVG(duration_ms)'))
            ->get()
            ->all();
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
}

<?php

declare(strict_types=1);

namespace Zufarmarwah\PerformanceGuard\Listeners;

use Illuminate\Database\Events\QueryExecuted;

class QueryListener
{
    /** @var array<int, array{sql: string, bindings: array, duration: float, file: string|null, line: int|null, normalized: string}> */
    private array $queries = [];

    private bool $listening = false;

    private bool $limitReached = false;

    public function start(): void
    {
        $this->queries = [];
        $this->listening = true;
        $this->limitReached = false;
    }

    public function stop(): void
    {
        $this->listening = false;
    }

    public function isListening(): bool
    {
        return $this->listening;
    }

    public function recordQuery(QueryExecuted $event): void
    {
        if (! $this->listening) {
            return;
        }

        $maxQueries = (int) config('performance-guard.thresholds.max_queries_per_request', 1000);

        if (count($this->queries) >= $maxQueries) {
            $this->limitReached = true;

            return;
        }

        $trace = $this->findSource();
        $normalized = $this->normalizeQuery($event->sql);

        $maxSqlLength = (int) config('performance-guard.thresholds.max_sql_length', 8000);
        $sql = $event->sql;

        if (strlen($sql) > $maxSqlLength) {
            $sql = substr($sql, 0, $maxSqlLength) . '... [truncated]';
        }

        $this->queries[] = [
            'sql' => $sql,
            'bindings' => $event->bindings,
            'duration' => $event->time,
            'file' => $trace['file'],
            'line' => $trace['line'],
            'normalized' => $normalized,
        ];
    }

    public function wasLimitReached(): bool
    {
        return $this->limitReached;
    }

    /**
     * @return array<int, array{sql: string, bindings: array, duration: float, file: string|null, line: int|null, normalized: string}>
     */
    public function getQueries(): array
    {
        return $this->queries;
    }

    public function getQueryCount(): int
    {
        return count($this->queries);
    }

    public function getTotalDuration(): float
    {
        $total = 0.0;

        foreach ($this->queries as $query) {
            $total += $query['duration'];
        }

        return $total;
    }

    /**
     * @return array<string, array{count: int, sql: string, queries: array}>
     */
    public function getDuplicates(): array
    {
        $grouped = [];

        foreach ($this->queries as $index => $query) {
            $key = $query['normalized'];

            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'count' => 0,
                    'sql' => $query['sql'],
                    'queries' => [],
                ];
            }

            $grouped[$key]['count']++;
            $grouped[$key]['queries'][] = $index;
        }

        $duplicates = [];

        foreach ($grouped as $key => $group) {
            if ($group['count'] > 1) {
                $duplicates[$key] = $group;
            }
        }

        return $duplicates;
    }

    public function hasDuplicates(int $threshold = 2): bool
    {
        $duplicates = $this->getDuplicates();

        foreach ($duplicates as $group) {
            if ($group['count'] >= $threshold) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, array{sql: string, bindings: array, duration: float, file: string|null, line: int|null, normalized: string}>
     */
    public function getSlowQueries(float $thresholdMs): array
    {
        $slow = [];

        foreach ($this->queries as $query) {
            if ($query['duration'] >= $thresholdMs) {
                $slow[] = $query;
            }
        }

        return $slow;
    }

    public function reset(): void
    {
        $this->queries = [];
        $this->listening = false;
        $this->limitReached = false;
    }

    /**
     * Normalize a SQL query by replacing literal values with placeholders.
     */
    private function normalizeQuery(string $sql): string
    {
        $normalized = preg_replace('/\b\d+\b/', '?', $sql) ?? $sql;
        $normalized = preg_replace("/'[^']*'/", '?', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }

    /**
     * Find the source file and line from the stack trace.
     *
     * @return array{file: string|null, line: int|null}
     */
    private function findSource(): array
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 30);

        $excludePatterns = [
            '/vendor\/laravel\/framework/',
            '/vendor\/zufarmarwah/',
            '/Illuminate/',
        ];

        foreach ($trace as $frame) {
            if (! isset($frame['file'])) {
                continue;
            }

            $excluded = false;

            foreach ($excludePatterns as $pattern) {
                if (preg_match($pattern, $frame['file'])) {
                    $excluded = true;
                    break;
                }
            }

            if (! $excluded) {
                return [
                    'file' => $frame['file'],
                    'line' => $frame['line'] ?? null,
                ];
            }
        }

        return ['file' => null, 'line' => null];
    }
}

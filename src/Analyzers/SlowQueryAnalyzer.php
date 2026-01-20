<?php

declare(strict_types=1);

namespace Zufarmarwah\PerformanceGuard\Analyzers;

class SlowQueryAnalyzer
{
    /**
     * Analyze queries for slow execution.
     *
     * @param  array<int, array{sql: string, normalized: string, duration: float, file: string|null, line: int|null}>  $queries
     * @return array{hasSlowQueries: bool, slowQueries: array<int, array{sql: string, duration: float, file: string|null, line: int|null, suggestion: string}>, totalSlowTime: float}
     */
    public function analyze(array $queries, float $thresholdMs = 300.0): array
    {
        $slowQueries = [];
        $totalSlowTime = 0.0;

        foreach ($queries as $query) {
            if ($query['duration'] < $thresholdMs) {
                continue;
            }

            $totalSlowTime += $query['duration'];

            $slowQueries[] = [
                'sql' => $query['sql'],
                'duration' => $query['duration'],
                'file' => $query['file'],
                'line' => $query['line'],
                'suggestion' => $this->generateSuggestion($query['sql'], $query['duration']),
            ];
        }

        usort($slowQueries, fn (array $a, array $b) => $b['duration'] <=> $a['duration']);

        return [
            'hasSlowQueries' => count($slowQueries) > 0,
            'slowQueries' => $slowQueries,
            'totalSlowTime' => round($totalSlowTime, 2),
        ];
    }

    private function generateSuggestion(string $sql, float $duration): string
    {
        $suggestions = [];

        if ($this->lacksIndex($sql)) {
            $suggestions[] = 'Consider adding an index on the filtered columns.';
        }

        if ($this->hasWildcardLike($sql)) {
            $suggestions[] = 'Leading wildcard in LIKE prevents index usage. Consider full-text search.';
        }

        if ($this->hasSelectAll($sql)) {
            $suggestions[] = 'Select only needed columns instead of SELECT *.';
        }

        if ($this->hasNoLimit($sql) && $this->isSelectQuery($sql)) {
            $suggestions[] = 'Consider adding a LIMIT clause to restrict result set size.';
        }

        if ($duration > 1000) {
            $suggestions[] = 'Query exceeds 1s. Consider caching or query optimization.';
        }

        if (empty($suggestions)) {
            $suggestions[] = 'Review query execution plan with EXPLAIN.';
        }

        return implode(' ', $suggestions);
    }

    private function lacksIndex(string $sql): bool
    {
        return (bool) preg_match('/\bwhere\b/i', $sql)
            && ! preg_match('/\busing\s+index\b/i', $sql);
    }

    private function hasWildcardLike(string $sql): bool
    {
        return (bool) preg_match("/like\s+'%/i", $sql);
    }

    private function hasSelectAll(string $sql): bool
    {
        return (bool) preg_match('/select\s+\*/i', $sql);
    }

    private function hasNoLimit(string $sql): bool
    {
        return ! preg_match('/\blimit\b/i', $sql);
    }

    private function isSelectQuery(string $sql): bool
    {
        return stripos(ltrim($sql), 'select') === 0;
    }
}

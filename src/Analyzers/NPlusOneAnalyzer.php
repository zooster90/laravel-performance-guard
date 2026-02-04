<?php

declare(strict_types=1);

namespace Zufarmarwah\PerformanceGuard\Analyzers;

class NPlusOneAnalyzer
{
    /**
     * Analyze queries for N+1 patterns.
     *
     * @param  array<int, array{sql: string, normalized: string, duration: float, file: string|null, line: int|null}>  $queries
     * @return array{hasNPlusOne: bool, duplicates: array<string, array{count: int, sql: string, indices: array<int>}>, suggestions: array<int, string>}
     */
    public function analyze(array $queries, int $threshold = 2): array
    {
        $grouped = $this->groupByNormalized($queries);
        $duplicates = $this->findDuplicates($grouped, $threshold);
        $suggestions = $this->generateSuggestions($duplicates);

        return [
            'hasNPlusOne' => count($duplicates) > 0,
            'duplicates' => $duplicates,
            'suggestions' => $suggestions,
        ];
    }

    /**
     * @param  array<int, array{sql: string, normalized: string, duration: float, file: string|null, line: int|null}>  $queries
     * @return array<string, array{count: int, sql: string, indices: array<int>}>
     */
    private function groupByNormalized(array $queries): array
    {
        $grouped = [];

        foreach ($queries as $index => $query) {
            $key = $query['normalized'];

            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'count' => 0,
                    'sql' => $query['sql'],
                    'indices' => [],
                    'file' => $query['file'] ?? null,
                    'line' => $query['line'] ?? null,
                ];
            }

            $grouped[$key]['count']++;
            $grouped[$key]['indices'][] = $index;
        }

        return $grouped;
    }

    /**
     * @param  array<string, array{count: int, sql: string, indices: array<int>}>  $grouped
     * @return array<string, array{count: int, sql: string, indices: array<int>}>
     */
    private function findDuplicates(array $grouped, int $threshold): array
    {
        $ignoredTables = config('performance-guard.ignored_tables', []);
        $duplicates = [];

        foreach ($grouped as $key => $group) {
            if ($group['count'] >= $threshold && $this->isSelectQuery($group['sql'])) {
                $table = $this->extractTableName($group['sql']);

                if ($table !== null && in_array($table, $ignoredTables, true)) {
                    continue;
                }

                $duplicates[$key] = $group;
            }
        }

        return $duplicates;
    }

    /**
     * @param  array<string, array{count: int, sql: string, indices: array<int>}>  $duplicates
     * @return array<int, string>
     */
    private function generateSuggestions(array $duplicates): array
    {
        $suggestions = [];

        foreach ($duplicates as $normalized => $group) {
            $table = $this->extractTableName($group['sql']);
            $count = $group['count'];
            $location = $this->formatLocation($group['file'] ?? null, $group['line'] ?? null);
            $parentTable = $this->extractParentTable($group['sql']);

            if ($table !== null && $this->isAggregateQuery($group['sql'])) {
                $suggestion = sprintf(
                    'Aggregate query on "%s" executed %d times. Consider using a subquery, DB::raw(), or precomputing these values instead of querying in a loop.',
                    $table,
                    $count
                );

                if ($location !== '') {
                    $suggestion .= ' ' . $location;
                }

                $suggestions[] = $suggestion;

                continue;
            }

            if ($table !== null) {
                $relationship = $this->guessRelationshipName($table);
                $parentModel = $parentTable !== null ? $this->tableToModel($parentTable) : null;

                $suggestion = sprintf(
                    'Query on "%s" executed %d times.',
                    $table,
                    $count
                );

                if ($parentModel !== null) {
                    $suggestion .= sprintf(
                        ' In your %s model, add ->with(\'%s\') to the query.',
                        $parentModel,
                        $relationship
                    );
                } else {
                    $suggestion .= sprintf(
                        ' Add ->with(\'%s\') to eager load this relationship.',
                        $relationship
                    );
                }

                if ($location !== '') {
                    $suggestion .= ' ' . $location;
                }

                $suggestions[] = $suggestion;
            } else {
                $suggestion = sprintf(
                    'Duplicate query executed %d times: %s. Consider eager loading or caching.',
                    $count,
                    mb_substr($group['sql'], 0, 100)
                );

                if ($location !== '') {
                    $suggestion .= ' ' . $location;
                }

                $suggestions[] = $suggestion;
            }
        }

        return $suggestions;
    }

    private function isSelectQuery(string $sql): bool
    {
        $trimmed = ltrim($sql);

        return stripos($trimmed, 'select') === 0;
    }

    private function isAggregateQuery(string $sql): bool
    {
        return (bool) preg_match('/\b(sum|count|avg|min|max)\s*\(/i', $sql)
            && (bool) preg_match('/\bas\s+aggregate\b/i', $sql);
    }

    private function extractTableName(string $sql): ?string
    {
        if (preg_match('/\bfrom\s+[`"]?(\w+)[`"]?/i', $sql, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function guessRelationshipName(string $table): string
    {
        $name = rtrim($table, 's');

        if (str_ends_with($table, 'ies')) {
            $name = substr($table, 0, -3) . 'y';
        }

        return $name;
    }

    /**
     * Extract the parent table from a WHERE clause like "WHERE post_id = ?".
     */
    private function extractParentTable(string $sql): ?string
    {
        if (preg_match('/where\s+[`"]?(\w+)_id[`"]?\s*=\s*(?:\?|\d+)/i', $sql, $matches)) {
            return $matches[1] . 's';
        }

        return null;
    }

    /**
     * Convert a table name to a model name (e.g., "posts" -> "Post").
     */
    private function tableToModel(string $table): string
    {
        $singular = rtrim($table, 's');

        if (str_ends_with($table, 'ies')) {
            $singular = substr($table, 0, -3) . 'y';
        }

        return ucfirst($singular);
    }

    private function formatLocation(?string $file, ?int $line): string
    {
        if ($file === null) {
            return '';
        }

        $basePath = base_path() . DIRECTORY_SEPARATOR;
        $relativePath = str_starts_with($file, $basePath)
            ? substr($file, strlen($basePath))
            : $file;

        if ($line !== null) {
            return sprintf('[%s:%d]', $relativePath, $line);
        }

        return sprintf('[%s]', $relativePath);
    }
}

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
        $duplicates = [];

        foreach ($grouped as $key => $group) {
            if ($group['count'] >= $threshold && $this->isSelectQuery($group['sql'])) {
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

            if ($table !== null) {
                $relationship = $this->guessRelationshipName($table);
                $suggestions[] = sprintf(
                    'Query on "%s" executed %d times. Consider adding ->with([\'%s\']) to eager load this relationship.',
                    $table,
                    $count,
                    $relationship
                );
            } else {
                $suggestions[] = sprintf(
                    'Duplicate query executed %d times: %s. Consider eager loading or caching.',
                    $count,
                    mb_substr($group['sql'], 0, 100)
                );
            }
        }

        return $suggestions;
    }

    private function isSelectQuery(string $sql): bool
    {
        $trimmed = ltrim($sql);

        return stripos($trimmed, 'select') === 0;
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
}
